<?php

namespace App\Command;

use DateTime;
use Doctrine\DBAL\Driver\Connection;
use ErrorException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ImportCommand extends Command
{
	protected static $defaultName        = 'app:import';
	protected static $defaultDescription = 'Imports UK postcodes to a DB';

	private string     $url;
	private Connection $connection;
	private array      $columnMap = [
		'postcode'  => 'pcds',
		'latitude'  => 'lat',
		'longitude' => 'long',
		//        'terminated' => 'doterm',
	];

	public function __construct(Connection $connection)
	{
		$this->connection = $connection;

		ProgressBar::setFormatDefinition('normal', '%message% [%bar%] %percent:3s%%');
		ProgressBar::setFormatDefinition('verbose', '%message% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
		ProgressBar::setFormatDefinition('very_verbose', '%message% [%bar%] %percent:3s%% (%current%/%max%) %elapsed:6s%/%estimated:-6s%');
		ProgressBar::setFormatDefinition('debug', '%message% [%bar%] %percent:3s%% (%current%/%max%) %elapsed:6s%/%estimated:-6s% %memory:6s%');

		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('url', InputArgument::OPTIONAL, 'URL for ONSPD data file (Optional)');
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$url = trim($input->getArgument('url'));
		if ($this->isValidUrl($url, $input, $output)) {
			$this->url = $url;
		}
	}

	protected function interact(InputInterface $input, OutputInterface $output)
	{
		if (!empty($this->url)) {
			return;
		}

		$questionHelper = $this->getHelper('question');
		$question       = new Question(
			'<question>URL for ONSPD data file?</question> ' .
			'<info>(Leave empty to download the latest version from \'http://parlvid.mysociety.org/os/\')</info>' . PHP_EOL
		);

		do {
			$url = trim($questionHelper->ask($input, $output, $question));
		} while (!$this->isValidUrl($url, $input, $output));

		$this->url = $url;
	}


	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$downloadedFile = $this->doDownload($output);
//        $downloadedFile = '/tmp/tmpElEOfU';
		$this->doImport($output, $downloadedFile);
        $this->doCleanUp($output, $downloadedFile);

		return 0;
	}

	/**
	 * @param OutputInterface $output
	 *
	 * @return string
	 */
	protected function doDownload(OutputInterface $output): string
	{
		if (!empty($this->url)) {
			$url = $this->url;
			$output->write(sprintf('Downloading %s ... ', $url));
			try {
				$downloadedFile = $this->downloadFile($url);
				$output->writeln('ok');

				return $downloadedFile;
			} catch (ErrorException $e) {
				$output->writeln('failed - ' . $e->getMessage());
			}
		}

		$earliest = new DateTime('18 months ago');
		$date     = new DateTime('first day of this month');
		do {
			$url = sprintf('https://parlvid.mysociety.org/os/ONSPD/%s.zip', $date->format('Y-m'));
			$output->write(sprintf('Downloading %s ... ', $url));
			try {
				$downloadedFile = $this->downloadFile($url);
				$output->writeln('ok');

				return $downloadedFile;
			} catch (ErrorException $e) {
				$output->writeln('failed - ' . $e->getMessage());
			}

			$date->modify('-1 month');
		} while ($earliest < $date);

		throw new RuntimeException('Download failed.');
	}

	/**
	 * @param OutputInterface $output
	 * @param string          $downloadedFile
	 */
	protected function doImport(OutputInterface $output, $downloadedFile)
	{
		$fileHandle = $this->getCsvFileHandle($downloadedFile, $fileSize);

		$progressBar = new ProgressBar($output, $fileSize);
		$progressBar->setMessage('Importing CSV into DB:');
		$progressBar->setRedrawFrequency(1000);
		$progressBar->start();

		$headerRow      = $this->readCsvLine($fileHandle, $bytesRead);
		$columnIndexMap = $this->getColumnIndexes($headerRow);
		$progressBar->advance($bytesRead);

		$statement = $this->connection->prepare($this->generateInsertSql());

		while (!feof($fileHandle)
//            && $progressBar->getProgressPercent() < .01
		) {
			$csvRow = $this->readCsvLine($fileHandle, $bytesRead);
			$statement->execute($this->getStatementParamsFromArray($csvRow, $columnIndexMap));
			$progressBar->advance($bytesRead);
		}

		$progressBar->finish();
		$output->writeln('');
	}

	/**
	 * @param OutputInterface $output
	 * @param string          $downloadedFile
	 */
	protected function doCleanUp(OutputInterface $output, $downloadedFile): void
	{
		$output->write('Removing temporary files ... ');
		if ($downloadedFile) {
			unlink($downloadedFile);
		}
		$output->writeln('done');
	}

	/**
	 * @param string          $url
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return bool
	 */
	protected function isValidUrl($url, InputInterface $input, OutputInterface $output): bool
	{
		if (!empty($url) &&
			!filter_var($url, FILTER_VALIDATE_URL)) {
			if ($input->isInteractive()) {
				$output->writeln(sprintf('<error>Invalid URL: %s</error>', $url));
			}

			return false;
		}

		return true;
	}

	/**
	 * @param string $url
	 *
	 * @return string Path of the downloaded file
	 * @throws ErrorException
	 */
	protected function downloadFile($url)
	{
		$curlHandle = curl_init($url);
		$file       = tempnam(sys_get_temp_dir(), 'tmp');
		$fileHandle = fopen($file, 'wb');

		curl_setopt($curlHandle, CURLOPT_FILE, $fileHandle);
		curl_setopt($curlHandle, CURLOPT_HEADER, 0);

		curl_exec($curlHandle);

		$errorEx = null;
		if (curl_errno($curlHandle) != 0) {
			$errorEx = new ErrorException(curl_error($curlHandle));
		} else {
			if (($responseCode = curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE)) != 200) {
				$responseText = isset(Response::$statusTexts[$responseCode]) ? Response::$statusTexts[$responseCode] : '';
				$errorEx      = new ErrorException(sprintf('HTTP Response: %d %s', $responseCode, $responseText));
			}

			curl_close($curlHandle);
		}

		fclose($fileHandle);

		if ($errorEx) {
			unlink($file);
			throw $errorEx;
		}

		return $file;
	}

	/**
	 * @param string  $downloadedFile
	 * @param integer $fileSize
	 *
	 * @return bool|resource
	 */
	protected function getCsvFileHandle($downloadedFile, &$fileSize = 0)
	{
		$zip = new ZipArchive();
		if ($zip->open($downloadedFile) === true) {
			$largestCsvStat = null;
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$stat = $zip->statIndex($i);
				if (strtolower(substr($stat['name'], -4)) == '.csv' &&
					(empty($largestCsvStat) || $largestCsvStat['size'] < $stat['size'])) {
					$largestCsvStat = $stat;
				}
			}

			if (empty($largestCsvStat)) {
				throw new RuntimeException('No CSV files found in archive.');
			}

			$fileHandle = $zip->getStream($largestCsvStat['name']);
			$fileSize   = $largestCsvStat['size'];

			$zip->close();
		} else {
			$fileHandle = fopen($downloadedFile, 'r');
			$fileSize   = filesize($downloadedFile);
		}

		return $fileHandle;
	}

	/**
	 * @param resource $fileHandle
	 * @param integer  $bytesRead
	 *
	 * @return array
	 */
	protected function readCsvLine($fileHandle, &$bytesRead = 0)
	{
		$line      = fgets($fileHandle);
		$bytesRead = strlen($line);

		return str_getcsv($line);
	}

	/**
	 * @param string[] $headerRow
	 *
	 * @return array
	 */
	protected function getColumnIndexes($headerRow)
	{
		$headerIndexes = array_flip($headerRow);

		return array_map(
			function ($col) use ($headerIndexes) {
				return $headerIndexes[$col] ?: null;
			},
			$this->columnMap
		);
	}

	/**
	 * @return string
	 */
	protected function generateInsertSql(): string
	{
		$columns       = array_keys($this->columnMap);
		$quotedColumns = '`' . implode('`, `', $columns) . '`';
		$namedParams   = ':' . implode(', :', $columns);

		return sprintf('replace into postcodes (%s) values (%s)', $quotedColumns, $namedParams);
	}

	/**
	 * @param $csvRow
	 * @param $columnIndexMap
	 *
	 * @return array
	 */
	protected function getStatementParamsFromArray($csvRow, $columnIndexMap): array
	{
		$importRow = array_map(
			function ($idx) use ($csvRow) {
				return $csvRow[$idx];
			},
			$columnIndexMap
		);

		return $importRow;
	}
}