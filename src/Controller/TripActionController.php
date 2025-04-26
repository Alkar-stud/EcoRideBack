<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\Trip;
use App\Entity\TripStatus;
use App\Entity\User;
use App\Repository\TripRepository;
use App\Service\MailService;
use App\Service\TripMongoService;
use App\Service\TripService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/trip', name: 'app_api_trip_')]
final class TripActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface  $manager,
        private readonly TripRepository          $repository,
        private readonly SerializerInterface     $serializer,
        private readonly MailService             $mailService,
        private readonly TripService             $tripService,
        private readonly TripRepository          $tripRepository,
        private readonly TripMongoService        $tripMongoService,
    )
    {
    }

    #[Route('/{id}/{action}', name: 'action', methods: ['PUT'])]
    public function action(#[CurrentUser] ?User $user, int $id, string $action): JsonResponse
    {
        //Est-ce que cette action est possible
        $isActionPossible = $this->tripService->isActionPossible($action, $id, $user);

        if ($isActionPossible['error'] != 'ok')
        {
            return new JsonResponse(['error' => $isActionPossible['message']], Response::HTTP_FORBIDDEN);
        }

        //Comme c'est possible, on persiste dans mysql et on supprime dans mongodb si $action == start
        //Tableau associatif des codes ⇒ id de covoiturageStatus
        $covoiturageStatusArray = $this->manager->getRepository(TripStatus::class)->createQueryBuilder('cs')
            ->select('cs.code, cs.id')
            ->getQuery()
            ->getResult();

        $covoiturageStatusMap = [];
        foreach ($covoiturageStatusArray as $status) {
            $covoiturageStatusMap[$status['code']] = $status['id'];
        }

        $possibleActions = $this->tripService->getPossibleActions();
        $statusId = $covoiturageStatusMap[$possibleActions[$action]["become"]];
        $status = $this->manager->getRepository(TripStatus::class)->find($statusId);
        if (!$status) {
            return new JsonResponse(['error' => true, 'message' => 'Le statut spécifié est introuvable'], Response::HTTP_NOT_FOUND);
        }
        // Supprimer le covoiturage de MongoDB car une fois démarré les visiteurs ne doivent plus le trouver ailleurs que dans leur espace utilisateur
        $trip = $this->tripRepository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if ($action == 'start') {
            $this->tripMongoService->delete($trip->getId());
        }
        //Récupérer les participants (user) du voyage
        $users = $trip->getUser()->map(function ($user) {
            return [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'email' => $user->getEmail(),
            ];
        })->toArray();

        //Si action = stop, on envoie un mail à tous les passagers pour qu'ils valident le trajet
        if ($action == 'stop')
        {
            //Envoi du mail type passengerValidation à tous les participants en remplaçant les données
            foreach ($users as $userForMailing) {
                $strToReplace = [
                    "pseudo" => $userForMailing['pseudo'],
                    "date" => $trip->getStartingAt()->format('d/m/Y'),
                    "arrivalAddress" => $trip->getArrivalAddress(),
                    "tripId" => $trip->getId(),
                ];

                $this->mailService->sendEmail($user->getEmail(), 'passengerValidation', $strToReplace);
            }

        }


        //Si action = cancel, on envoie un mail à tous les passagers pour les prévenir
        if ($action == 'cancel')
        {
            //Envoi du mail type cancelTrip à tous les participants en remplaçant les données
            foreach ($users as $userForMailing) {
                $strToReplace = [
                    "pseudo" => $userForMailing['pseudo'],
                    "date" => $trip->getStartingAt()->format('d/m/Y'),
                    "arrivalAddress" => $trip->getArrivalAddress()
                ];

                $this->mailService->sendEmail($user->getEmail(), 'cancel', $strToReplace);
            }

            //Suppression du document dans MongoDB
            $confirmDeleteMongo = $this->tripMongoService->delete($trip->getId());

        }

        $trip->setStatus($status);
        $trip->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        //Texte à retourner selon l'action réalisée
        $returnMessage = match ($action) {
            'start' => 'Le voyage commence !',
            'stop' => 'Tout le monde est bien arrivé ?',
            'cancel' => 'Le covoiturage est annulé',
            'badxp' => 'Le covoiturage est soumis à un contrôle de la plateforme',
            'finish' => 'Le covoiturage est terminé et clos',
            default => 'Cette action est impossible dans cet état.',
        };

        return new JsonResponse(['message' => $returnMessage], Response::HTTP_OK);
    }

}
