<?php

namespace App\Controller;

use App\Repository\PostcodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
	/**
	 * @Route("/", name="index")
	 *
	 * @return Response
	 */
	public function index(PostcodeRepository $postcodeRepository)
	{
		try {
			$count = $postcodeRepository->count([]);
		} catch (\Throwable $e) {
			$count = 0;
		}

		return $this->render('pages/index.html.twig', ['setupRequired' => $count == 0]);
	}
}