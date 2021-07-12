<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PostcodeLookupController extends AbstractController
{
	/** @var Connection */
	private $connection;

	/**
	 * PostcodeLookupController constructor.
	 *
	 * @param Connection $connection
	 */
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * @Route("/api/postcode-lookup-by-string", name="api_postcode_lookup_by_string")
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function lookupByString(Request $request)
	{
		$searchString = $request->query->get('search');

		$stmt = $this->connection->prepare("SELECT * FROM postcodes WHERE postcode LIKE ? ORDER BY 1 limit 20");

        $stmt->execute(['%' . $searchString . '%']);

        return $this->json($stmt->fetchAllAssociative());
    }

	/**
	 * @Route("/api/postcode-lookup-by-location", name="api_postcode_lookup_by_location")
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function lookupByLocation(Request $request)
	{
		$latitude  = $request->query->get('latitude');
		$longitude = $request->query->get('longitude');
		$distance  = $request->query->get('distance');

		$sql  = <<<EOT
select
       *, 
       3959 * acos(
           cos(radians(latitude)) * cos(radians(:latitude)) * cos(radians(:longitude) - radians(longitude))
           + sin(radians(latitude))* sin(radians(:latitude))
       ) AS distance
from postcodes
having distance < :distance
order by distance
limit 20
EOT;
		$stmt = $this->connection->prepare($sql);

		$stmt->execute(
			[
				':latitude'  => $latitude,
				':longitude' => $longitude,
				':distance'  => $distance,
			]
		);

		return $this->json($stmt->fetchAllAssociative());
	}
}
