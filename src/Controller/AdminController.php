<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Repository\UserRepository;
use App\Repository\LoginHistoryRepository;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(
        UserRepository $userRepository,
        LoginHistoryRepository $loginHistoryRepository
    ): Response {
        $users = $userRepository->findBy([], ['id' => 'ASC']);

        $loginHistoriesByUserId = [];

        $loginHistories = $loginHistoryRepository->findBy(
            [],
            ['loggedAt' => 'DESC']
        );

        foreach ($loginHistories as $loginHistory) {
            $userId = $loginHistory->getUser()->getId();

            $loginHistoriesByUserId[$userId][] = $loginHistory;
        }

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'loginHistoriesByUserId' => $loginHistoriesByUserId,
        ]);
    }
}
