<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\FormError;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\LoginHistoryRepository;
use App\Form\UserEditFormType;
use App\Form\UserRolesFormType;

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

    #[Route('/admin/user/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(UserEditFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Les informations de l’utilisateur ont été modifiées.'
            );

            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/edit_user.html.twig', [
            'userForm' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/{id}/roles', name: 'app_admin_user_roles', methods: ['GET', 'POST'])]
    public function editRoles(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(UserRolesFormType::class, $user);

        $form->get('roles')->setData(
            in_array('ROLE_ADMIN', $user->getRoles(), true)
                ? ['ROLE_ADMIN']
                : []
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedRoles = $form->get('roles')->getData();

            if ($user === $this->getUser() && !in_array('ROLE_ADMIN', $selectedRoles, true)) {
                $form->get('roles')->addError(
                    new FormError('Vous ne pouvez pas retirer vos propres privilèges administrateur.')
                );
            } else {
                $user->setRoles($selectedRoles);

                $entityManager->flush();

                $this->addFlash(
                    'success',
                    'Les privilèges de l’utilisateur ont été modifiés.'
                );

                return $this->redirectToRoute('app_admin');
            }
        }

        return $this->render('admin/edit_roles.html.twig', [
            'rolesForm' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/admin/user/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        LoginHistoryRepository $loginHistoryRepository
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete-user-' . $user->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($this->getUser() === $user) {
            $this->addFlash(
                'error',
                'Vous ne pouvez pas supprimer votre propre compte.'
            );

            return $this->redirectToRoute('app_admin');
        }

        $connection = $entityManager->getConnection();

        $connection->beginTransaction();

        try {
            $loginHistories = $loginHistoryRepository->findBy([
                'user' => $user,
            ]);

            foreach ($loginHistories as $loginHistory) {
                $entityManager->remove($loginHistory);
            }


            $entityManager->remove($user);
            $entityManager->flush();

            $entityManager->commit();
        } catch (\Throwable $exception) {
            $connection->rollback();

            throw $exception;
        }

        $this->addFlash(
            'success',
            'L’utilisateur et son historique de connexion ont été supprimés.'
        );

        return $this->redirectToRoute('app_admin');
    }
}
