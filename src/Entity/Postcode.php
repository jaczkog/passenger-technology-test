<?php

namespace App\Entity;

//use App\Repository\PostcodeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Postcodes
 *
 * @ORM\Table(name="postcodes")
 * @ORM\Entity(repositoryClass="App\Repository\PostcodeRepository")
 */
class Postcode
{
	/**
	 * @var string
	 *
	 * @ORM\Column(name="postcode", type="string", length=9, nullable=false)
	 * @ORM\Id
	 */
	private $postcode;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="latitude", type="float", precision=10, scale=0, nullable=false)
	 */
	private $latitude;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="longitude", type="float", precision=10, scale=0, nullable=false)
	 */
	private $longitude;

	/**
	 * @var \DateTime|null
	 *
	 * @ORM\Column(name="terminated", type="date", nullable=true)
	 */
	private $terminated;

	public function getPostcode(): ?string
	{
		return $this->postcode;
	}

	public function setPostcode(string $postcode): self
	{
		$this->postcode = $postcode;

		return $this;
	}

	public function getLatitude(): ?float
	{
		return $this->latitude;
	}

	public function setLatitude(float $latitude): self
	{
		$this->latitude = $latitude;

		return $this;
	}

	public function getLongitude(): ?float
	{
		return $this->longitude;
	}

	public function setLongitude(float $longitude): self
	{
		$this->longitude = $longitude;

		return $this;
	}

	public function getTerminated(): ?\DateTimeInterface
	{
		return $this->terminated;
	}

	public function setTerminated(?\DateTimeInterface $terminated): self
	{
		$this->terminated = $terminated;

		return $this;
	}
}
