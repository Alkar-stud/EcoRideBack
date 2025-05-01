<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Entity\TripStatus;
use App\Entity\User;
use App\Repository\TripRepository;
use App\Service\MailService;
use App\Service\TripMongoService;
use App\Service\TripService;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\Attribute\Areas;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/trip', name: 'app_api_trip_')]
#[OA\Tag(name: 'TripAction')]
#[Areas(["default"])]
final class TripActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface  $manager,
        private readonly MailService             $mailService,
        private readonly TripService             $tripService,
        private readonly TripRepository          $tripRepository,
        private readonly TripMongoService        $tripMongoService,
    )
    {
    }

    #[Route('/{tripId}/addUser', name: 'addUser', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/trip/{tripId}/addUser",
        summary:"Ajout d'un participant",

    )]
    #[OA\Response(
        response: 200,
        description: 'Vous avez été ajouté à ce covoiturage'
    )]
    #[OA\Response(
        response: 400,
        description: 'Covoiturage non trouvé'
    )]
    #[OA\Response(
        response: 402,
        description: 'Vous n\'avez pas assez de credit pour participer à ce covoiturage.'
    )]
    public function addUser(#[CurrentUser] ?User $user, int $tripId): JsonResponse
    {
        //Récupération du covoiturage
        $trip = $this->tripRepository->findOneBy(['id' => $tripId]);

        //Si le statut est le statut initial ET que le user n'a pas déjà été ajouté
        if ($trip->getStatus() === $this->tripService->getDefaultStatus())
        {
            //Si le $user fait partie des participants
            if ($trip->getUser()->contains($user)) {
                // L'utilisateur fait partie des participants
                return new JsonResponse(['message' => 'Vous êtes déjà inscrit à ce covoiturage.'], Response::HTTP_OK);
            }
            //Vérification que le solde de credit est suffisant
            if ($user->getCredits() < $trip->getNbCredit()) {
                return new JsonResponse(['message' => 'Vous \'avez pas assez de credit pour participer à ce covoiturage.'], Response::HTTP_PAYMENT_REQUIRED);
            }
            $user->setCredits($user->getCredits() - $trip->getNbCredit());
            //On met à jour le nombre de places restantes
            $trip->setNbPlaceRemaining($trip->getNbPlaceRemaining() - 1);
            $trip->addUser($user);
            $this->manager->flush();

            //On met à jour nbPlaceRemaining et nbParticipant dans MongoDB
            $users = $trip->getUser();
            count($users) ? $nbParticipant = count($users): $nbParticipant = 0;
            //Modification dans mongoDB
            $this->tripMongoService->update($trip->getId(), [
                'id_covoiturage' => $trip->getId(),
                'startingAddress' => $trip->getStartingAddress(),
                'arrivalAddress' => $trip->getArrivalAddress(),
                'startingAt' => $trip->getStartingAt(),
                'duration' => $trip->getDuration(),
                'nbCredit' => $trip->getNbCredit(),
                'nbPlaceRemaining' => $trip->getNbPlaceRemaining(),
                'nbParticipant' => $nbParticipant,
                'vehicle' => [
                    'energy' => $trip->getVehicle()?->getEnergy()?->getLibelle(),
                    'isEco' => $trip->getVehicle()?->getEnergy()?->isEco(),
                ],
            ]);

            return new JsonResponse(['message'=>'Vous avez été ajouté à ce covoiturage'], Response::HTTP_OK);
        }
        return new JsonResponse(['message'=>'L\'état de ce covoiturage ne permet pas l\'ajout de participants'], Response::HTTP_FORBIDDEN);
    }

    #[Route('/{tripId}/removeUser', name: 'removeUser', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/trip/{tripId}/removeUser",
        summary:"Retrait d'un participant",
    )]
    #[OA\Response(
        response: 200,
        description: 'Vous avez été retiré de ce covoiturage'
    )]
    #[OA\Response(
        response: 400,
        description: 'Covoiturage non trouvé'
    )]
    public function removeUser(#[CurrentUser] ?User $user, int $tripId): JsonResponse
    {
        //Récupération du covoiturage
        $trip = $this->tripRepository->findOneBy(['id' => $tripId]);

        //Si le statut est le statut initial ET que le user n'a pas déjà été ajouté
        if ($trip->getStatus() === $this->tripService->getDefaultStatus())
        {
            //Si le $user fait partie des participants
            if (!$trip->getUser()->contains($user)) {
                // L'utilisateur ne fait pas partie des participants
                return new JsonResponse(['message' => 'Vous n\'êtes pas inscrit à ce covoiturage.'], Response::HTTP_OK);
            }
            //On recrédite le user
            $user->setCredits($user->getCredits() + $trip->getNbCredit());
            //On met à jour le nombre de places restantes
            $trip->setNbPlaceRemaining($trip->getNbPlaceRemaining() + 1);
            $trip->removeUser($user);
            $this->manager->flush();

            //On met à jour nbPlaceRemaining et nbParticipant dans MongoDB
            $users = $trip->getUser();
            count($users) ? $nbParticipant = count($users): $nbParticipant = 0;
            //Modification dans mongoDB
            $this->tripMongoService->update($trip->getId(), [
                'id_covoiturage' => $trip->getId(),
                'startingAddress' => $trip->getStartingAddress(),
                'arrivalAddress' => $trip->getArrivalAddress(),
                'startingAt' => $trip->getStartingAt(),
                'duration' => $trip->getDuration(),
                'nbCredit' => $trip->getNbCredit(),
                'nbPlaceRemaining' => $trip->getNbPlaceRemaining(),
                'nbParticipant' => $nbParticipant,
                'vehicle' => [
                    'energy' => $trip->getVehicle()?->getEnergy()?->getLibelle(),
                    'isEco' => $trip->getVehicle()?->getEnergy()?->isEco(),
                ],
            ]);

            return new JsonResponse(['message'=>'Vous avez été retiré à ce covoiturage'], Response::HTTP_OK);
        }
        return new JsonResponse(['message'=>'L\'état de ce covoiturage ne permet pas de retirer des participants'], Response::HTTP_FORBIDDEN);
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/{tripId}/{action}', name: 'action', methods: ['PUT'])]
    public function action(#[CurrentUser] ?User $user, int $tripId, string $action): JsonResponse
    {
        //Est-ce que cette action est possible
        $isActionPossible = $this->tripService->isActionPossible($action, $tripId, $user);

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

        //Récupération du tableau des actions possibles
        $possibleActions = $this->tripService->getPossibleActions();
        //Récupération de l'ID du statut suivant.
        $statusId = $covoiturageStatusMap[$possibleActions[$action]["become"]];
        //Récupération de l'entité du statut suivant
        $status = $this->manager->getRepository(TripStatus::class)->find($statusId);
        if (!$status) {
            return new JsonResponse(['error' => true, 'message' => 'Le statut spécifié est introuvable'], Response::HTTP_NOT_FOUND);
        }

        //Récupération du covoiturage
        $trip = $this->tripRepository->findOneBy(['id' => $tripId, 'owner' => $user->getId()]);
        //Récupérer les participants (user) du voyage
        $users = $trip->getUser()->map(function ($tripUser) {
            return [
                'id' => $tripUser->getId(),
                'pseudo' => $tripUser->getPseudo(),
                'email' => $tripUser->getEmail(),
            ];
        })->toArray();



        // Supprimer le covoiturage de MongoDB car une fois démarré les visiteurs ne doivent plus le trouver ailleurs que dans leur espace utilisateur
        if ($action == 'start') {
            $this->tripMongoService->delete($trip->getId());
            //on enregistre la date/heure de départ réelle
            $trip->setActualDepartureAt(new DateTimeImmutable());
        }

        //Si action = stop, on envoie un mail à tous les passagers pour qu'ils valident le trajet
        //Si action = cancel, on envoie un mail à tous les passagers pour les prévenir.
        if (in_array($action, ['cancel', 'stop']))
        {
            //tableau des mailtype selon action
            $mailType = ['cancel' => 'cancel', 'stop' => 'passengerValidation'];
            //Envoi du mail type cancelTrip à tous les participants en remplaçant les données
            // ou
            //Envoi du mail type passengerValidation à tous les participants en remplaçant les données
            foreach ($users as $userForMailing) {
                $participant = $this->manager->getRepository(User::class)->find($userForMailing['id']);
                $strToReplace = [
                    "pseudo" => $userForMailing['pseudo'],
                    "date" => $trip->getStartingAt()->format('d/m/Y'),
                    "arrivalAddress" => $trip->getArrivalAddress(),
                    "tripId" => $trip->getId(),
                ];
                //on recrédite les participants
                $participant->setCredits($participant->getCredits() + $trip->getNbCredit());
                $this->mailService->sendEmail($userForMailing['email'], $mailType[$action], $strToReplace);
            }

            //Si cancel, on supprime le document dans MongoDB
            if ($action == 'cancel') { $this->tripMongoService->delete($trip->getId()); }
            //Si stop, on enregistre la date et l'heure d'arrivée
            if ($action == 'stop') { $trip->setArrivalAt(new DateTimeImmutable()); }

        }
        //Si finished, on ajoute les crédits au chauffeur : $user->setCredit(count($users) * $trip->getNbCredit() + $user->getCredit()));
        if ($action == 'finish') {
            $user->setCredits(count($users) * $trip->getNbCredit() + $user->getCredits());
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
