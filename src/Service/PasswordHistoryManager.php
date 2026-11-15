<?php

namespace App\Service;

use App\Entity\PasswordHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordHistoryManager
{
    private const HISTORY_LIMIT = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * Change le mot de passe si celui-ci n'a jamais été utilisé.
     *
     * Retourne false si le mot de passe a déjà été utilisé.
     */
    public function changePassword(User $user, string $newPlainPassword): bool
    {
        if ($this->isPasswordAlreadyUsed($user, $newPlainPassword)) {
            return false;
        }

        $this->makeRoomForNewHistoryEntry($user);

        $currentPassword = $user->getPassword();

        if ($currentPassword !== null) {
            $history = new PasswordHistory();
            $history->setHashedPassword($currentPassword);
            $history->setCreatedAt(new \DateTimeImmutable());

            $user->addPasswordHistory($history);

            $this->entityManager->persist($history);
        }

        $newHashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $newPlainPassword
        );

        $user->setPassword($newHashedPassword);

        $this->entityManager->flush();

        return true;
    }

    /**
     * Vérifie le mot de passe actuel et les anciens mots de passe.
     */
    private function isPasswordAlreadyUsed(
        User $user,
        string $plainPassword
    ): bool {
        if ($this->userPasswordHasher->isPasswordValid($user, $plainPassword)) {
            return true;
        }

        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);

        foreach ($user->getPasswordHistories() as $history) {
            $hashedPassword = $history->getHashedPassword();

            if (
                $hashedPassword !== null
                && $passwordHasher->verify(
                    $hashedPassword,
                    $plainPassword
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Conserve au maximum quatre anciens hashes avant le nouvel archivage.
     */
    private function makeRoomForNewHistoryEntry(User $user): void
    {
        $histories = $user->getPasswordHistories()->toArray();

        usort($histories, static function (
            PasswordHistory $a,
            PasswordHistory $b
        ): int {
            $dateComparison = (
                $a->getCreatedAt()?->getTimestamp() ?? 0
            ) <=> (
                $b->getCreatedAt()?->getTimestamp() ?? 0
            );

            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        while (count($histories) >= self::HISTORY_LIMIT) {
            $oldestHistory = array_shift($histories);

            if ($oldestHistory === null) {
                break;
            }

            $user->removePasswordHistory($oldestHistory);
            $this->entityManager->remove($oldestHistory);
        }
    }
}
