<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Document\MongoRideNotice;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Areas;

#[Route('/api/notices', name: 'app_api_notices_')]
#[OA\Tag(name: 'Notices')]
#[Areas(["default"])]
final class NoticesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentManager $documentManager
    ) {
    }

    /**
     * Calcule et met à jour la note globale de l'utilisateur
     */
    public function calculateUserGrade(int $userId): JsonResponse
    {
        // Récupérer l'utilisateur
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Récupérer tous les covoiturages de l'utilisateur (en tant que conducteur)
        $rides = $this->entityManager->getRepository(Ride::class)
            ->findBy(['driver' => $user]);

        if (empty($rides)) {
            return new JsonResponse(['message' => 'Cet utilisateur n\'a aucun trajet en tant que conducteur'], Response::HTTP_OK);
        }

        $totalRidesWithGrades = 0;
        $totalGradesSum = 0;

        // Pour chaque ride, récupérer les notes dans MongoDB
        foreach ($rides as $ride) {
            $rideId = $ride->getId();

            // Rechercher les notices pour ce ride dans MongoDB
            $notices = $this->documentManager->getRepository(MongoRideNotice::class)
                ->findBy(['relatedFor' => $rideId, 'status' => 'VALIDATED']);

            $ridesGrades = [];

            foreach ($notices as $notice) {
                $grade = $notice->getGrade();
                if ($grade !== null) {
                    $ridesGrades[] = $grade;
                }
            }

            // S'il y a des notes pour ce ride, calculer la moyenne
            if (!empty($ridesGrades)) {
                $rideAvgGrade = array_sum($ridesGrades) / count($ridesGrades);
                $totalGradesSum += $rideAvgGrade;
                $totalRidesWithGrades++;
            }
        }

        // Calculer la moyenne globale sur 10 et arrondir la note
        if ($totalRidesWithGrades > 0) {
            $globalGrade = ($totalGradesSum / $totalRidesWithGrades);
            $globalGrade = round($globalGrade);

            // Mettre à jour le grade de l'utilisateur
            $user->setGrade($globalGrade);
            $user->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse([
                'message' => 'La note globale a été calculée et mise à jour avec succès',
                'userId' => $userId,
                'globalGrade' => $globalGrade,
                'totalRidesWithGrades' => $totalRidesWithGrades
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'message' => 'Aucune note trouvée pour les trajets de cet utilisateur',
            'userId' => $userId
        ], Response::HTTP_OK);
    }

    /**
     * Trouve un utilisateur par son ID ou renvoie null
     */
    private function findUserOrRespond(int $userId): ?User
    {
        return $this->entityManager->getRepository(User::class)->find($userId);
    }

    /**
     * Récupère tous les avis validés d'un utilisateur conducteur
     */
    #[Route('/{userId}', name: 'get_user_notices', methods: ['GET'])]
    #[OA\Get(
        path: "/api/notices/{userId}",
        summary: "Récupère toutes les notes validées d'un utilisateur conducteur"
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des notes récupérées avec succès'
    )]
    public function getUserNotices(int $userId): JsonResponse
    {
        $user = $this->findUserOrRespond($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $rides = $this->entityManager->getRepository(Ride::class)
            ->findBy(['driver' => $user]);

        $noticesData = $this->getNoticesData($rides);

        return new JsonResponse([
            'userId' => $userId,
            'pseudo' => $user->getPseudo(),
            'currentGrade' => $user->getGrade(),
            'ridesNotices' => $noticesData
        ], Response::HTTP_OK);
    }

    /**
     * Collecte les données des avis pour une liste de trajets
     */
    private function getNoticesData(array $rides): array
    {
        $noticesData = [];

        foreach ($rides as $ride) {
            $rideId = $ride->getId();
            $notices = $this->documentManager->getRepository(MongoRideNotice::class)
                ->findBy(['relatedFor' => $rideId, 'status' => 'VALIDATED'], ['createdAt' => -1]);

            $rideNotices = array_map(function($notice) {
                return [
                    'title' => $notice->getTitle(),
                    'content' => $notice->getContent(),
                    'grade' => $notice->getGrade(),
                    'status' => $notice->getStatus(),
                    'createdAt' => $notice->getCreatedAt() ? $notice->getCreatedAt()->format('Y-m-d H:i:s') : null,
                ];
            }, $notices);

            if (!empty($rideNotices)) {
                $noticesData[] = [
                    'startingCity' => $ride->getStartingCity(),
                    'arrivalCity' => $ride->getArrivalCity(),
                    'startingAt' => $ride->getStartingAt() ? $ride->getStartingAt()->format('Y-m-d H:i:s') : null,
                    'notices' => $rideNotices
                ];
            }
        }

        return $noticesData;
    }
}
