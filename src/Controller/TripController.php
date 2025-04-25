<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use App\Service\TripMongoService;
use App\Service\TripService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Mailer\MailerInterface;

#[Route('/api/trip', name: 'app_api_trip_')]
final class TripController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface  $manager,
        private readonly TripRepository          $repository,
        private readonly SerializerInterface     $serializer,
        private readonly TripService             $tripService,
        private readonly TripMongoService        $tripMongoService,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $trip = $this->serializer->deserialize($request->getContent(), Trip::class, 'json');
        //Récupération des autres données
        $data = json_decode($request->getContent(), true);
        //Vérification si le champ 'vehicle' existe
        if (!isset($data['vehicle']))
        {
            return new JsonResponse('Un véhicule vous appartenant est obligatoire', Response::HTTP_BAD_REQUEST);
        }
        //ajout Vehicle à Trip
        if ($this->tripService->getTripVehicle($data['vehicle'], $user) === null)
        {
            return new JsonResponse('Un véhicule vous appartenant est obligatoire', Response::HTTP_BAD_REQUEST);
        }
        $trip->setVehicle($this->tripService->getTripVehicle($data['vehicle'], $user));
        //ajout Owner = CurrentUser à Trip
        $trip->setOwner($user);
        //ajout Status correspondant au statut par défaut
        $trip->setStatus($this->tripService->getDefaultStatus());
        //Ajout durée du voyage
        if (!isset($data['duration'])) {
            return new JsonResponse('La durée du voyage est obligatoire', Response::HTTP_BAD_REQUEST);
        }
        $trip->setDuration($data['duration']);


        $trip->setCreatedAt(new DateTimeImmutable());
        $this->manager->persist($trip);
        $this->manager->flush();

        // Vérification des préférences après rechargement
        $preferences = $user->getPreferences()->toArray();
        if (empty($preferences)) {
            return new JsonResponse(['error' => 'Aucune préférence trouvée pour cet utilisateur.'], Response::HTTP_BAD_REQUEST);
        }

        $user->getPreferences()->toArray();

        // Sérialiser les préférences de l'utilisateur pour MongoDB
        $preferencesArray = $this->serializer->normalize($user->getPreferences(), null, ['groups' => 'preferences_user']);
        // Ajouter les préférences sérialisées dans MongoDB
        $this->tripMongoService->add([
            'id_covoiturage' => $trip->getId(),
            'status' => $trip->getStatus()?->getLibelle(),
            'startingAddress' => $trip->getStartingAddress(),
            'arrivalAddress' => $trip->getArrivalAddress(),
            'startingAt' => $trip->getStartingAt(),
            'duration' => $trip->getDuration(),
            'nbCredit' => $trip->getNbCredit(),
            'nbPlaceRemaining' => $trip->getNbPlaceRemaining(),
            'nbParticipant' => 0,
            // Données utilisateur
            'user' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'preferences' => $preferencesArray,
                'grade' => $user->getGrade(),
            ],
            // Données véhicule
            'vehicle' => [
                'brand' => $trip->getVehicle()->getBrand(),
                'model' => $trip->getVehicle()->getModel(),
                'color' => $trip->getVehicle()->getColor(),
                'energy' => $trip->getVehicle()->getEnergy()?->getLibelle(),
                'isEco' => $trip->getVehicle()->getEnergy()?->isEco(),
            ],
            'createdAt' => $trip->getCreatedAt(),
        ]);

        return new JsonResponse(
            [
                'id'  => $trip->getId(),
                'status'  => [
                    'libelle' => $trip->getStatus()?->getLibelle()
                ],
                'startingAddress'  => $trip->getStartingAddress(),
                'arrivalAddress'  => $trip->getArrivalAddress(),
                'startingAt' => $trip->getStartingAt(),
                'tripDuration' => $trip->getDuration(),
                'nbCredit' => $trip->getNbCredit(),
                'nbPlace' => $trip->getNbPlaceRemaining(),
                'createdAt' => $trip->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list/{state}', name: 'showAll', methods: 'GET')]
    public function showAllOwner(#[CurrentUser] ?User $user, Request $request, string $state): JsonResponse
    {
        $possibleCodeStatus = $this->tripService->getPossibleStatus();
        // Pagination pour les covoiturages
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        //Vérification si l'état demandé existe
        if (!array_key_exists($state, $possibleCodeStatus) && $state !== 'all')
        {
            return new JsonResponse(['error' => true, 'message' => 'Cet état n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }

        //Si l'état demandé est 'all' pour tout afficher
        if ($state !== 'all')
        {
            $trips = $this->repository->findBy(
                ['owner' => $user->getId(), 'status' => $possibleCodeStatus[$state]],
                ['startingAt' => 'ASC'],
                $limit,
                ($page - 1) * $limit
            );
        }
        else
        {
            $trips = $this->repository->findBy(
                ['owner' => $user->getId()],
                ['startingAt' => 'ASC'],
                $limit,
                ($page - 1) * $limit
            );
        }


        if ($trips) {
            $responseData = $this->serializer->serialize(
                $trips,
                'json',
                ['groups' => ['trip_detail']]
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(['message' => 'Il n\'y a pas de covoiturage pour cet utilisateur.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $trip = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);

        if (!$trip) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        $responseData = $this->serializer->serialize(
            $trip,
            'json',
            ['groups' => ['trip_detail']]
        );
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/edit/{id}/update', name: 'edit', methods: ['PUT'])]
    public function edit(#[CurrentUser] ?User $user, Request $request, int $id, MailerInterface $mailer): JsonResponse
    {
        $trip = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        $originalVehicleId = $trip->getVehicle()->getId();
        if (!$trip) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        //Seul l'update des datas sera traité ici, possible en fonction du statut du covoiturage, donc le statut sera modifié ailleurs. Owner n'est pas modifiable non plus
        $possibleActions = $this->tripService->getPossibleActions();
        //Vérification si l'action 'update' existe, et si elle est possible en fonction du statut du covoiturage
        if (!array_key_exists('update', $possibleActions) || !in_array($trip->getStatus()?->getCode(), $possibleActions['update']['initial']))
        {
            $returnMessage = [
                "error" => true,
                "message" => "Le covoiturage ne peut pas être modifié en l\'état.",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        //Récupérer les participants (user) du voyage
        $users = $trip->getUser()->map(function ($user) {
            return [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'email' => $user->getEmail(),
            ];
        })->toArray();


        //Modification impossible si des participants sont inscrits, sauf si on augmente le nombre de places

        /* Si participants == 0 → on peut modifier (presque) tout
         * Si participants > 0 →
         *   → Si nbPlaceRemaining >= count($users) => on peut modifier ce champ et vehicle.
         */

        $champsModifiables = [
            0=>"vehicle",
            1=>"startingAddress",
            2=>"arrivalAddress",
            3=>"startingAt",
            4=>"duration",
            5=>"nbCredit",
            6=>"nbPlaceRemaining"
        ];
        //S'il y a plus de participants que de places à renseigner et qu'on veut modifier le nombre de places restantes, on ne peut rien modifier.
        if (array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) > $dataRequest['nbPlaceRemaining'])
        {
            $returnMessage = [
                "error" => true,
                "message" => "Vous ne pouvez pas mettre moins de places que de participants",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }
        //S'il y a plus ou égal nbPlaceRemaining que de participants, on ne peut que modifier le nb de place et le véhicule.
        if (array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) <= $dataRequest['nbPlaceRemaining'] && count($users) > 0)
        {
            unset($champsModifiables[1], $champsModifiables[2], $champsModifiables[3], $champsModifiables[4], $champsModifiables[5]);
        }
        //si changement du nombre de places restantes, mais inférieur au nombre de participants, on ne peut rien modifier.
        if (array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) > $dataRequest['nbPlaceRemaining'])
        {
            $returnMessage = [
                "error" => true,
                "message" => "Il y a plus de participant que le nombre de places disponibles demandées",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }
        //Si pas de changement de places restantes à changer, mais au moins 1 participant, on ne peut rien modifier
        if (!array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) > 0)
        {
            $returnMessage = [
                "error" => true,
                "message" => "Vous ne pouvez pas modifier ce covoiturage lorsqu'il y a des participants",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }

        //Si la clé de la requête existe en valeur dans $champsModifiables, on fait la modification
        foreach ($dataRequest as $key => $value) {
            if (in_array($key, $champsModifiables)) {
                $setter = 'set' . ucfirst($key);
                if (method_exists($trip, $setter)) {
                    $reflectionMethod = new \ReflectionMethod($trip, $setter);
                    $parameters = $reflectionMethod->getParameters();

                    if (!empty($parameters)) {
                        $parameterType = $parameters[0]->getType();
                        if ($parameterType && !$parameterType->isBuiltin()) {
                            $className = $parameterType->getName();
                            if (class_exists($className)) {
                                $value = $this->manager->getRepository($className)->find($value);
                                if (!$value) {
                                    throw new \InvalidArgumentException("L'entité {$className} avec l'ID spécifié est introuvable.");
                                }
                            }
                        }
                    }
                    $trip->$setter($value);
                }
            }
        }

        //Si on a changé le véhicule, on envoie un mail
        if ($trip->getVehicle()->getId() !== $originalVehicleId)
        {
            //Envoi du mail type changeTripVehicle à tous les participants
            //récupération des datas du mailtype 'changeTripVehicle'
            $mail = $this->manager->getRepository(Mail::class)->findOneBy(['typeMail' => 'changeTripVehicle']);

            if (!$mail) {
                return new JsonResponse(['error' => 'Aucun mail trouvé pour le type "changeTripVehicle".'], Response::HTTP_NOT_FOUND);
            }

            // Utilisation des données de l'entité Mail
            foreach ($users as $userForMailing)
            {
                $contentMail = $mail->getContent();
                $contentMail = str_replace('{pseudo}',$userForMailing['pseudo'],$contentMail);
                $contentMail = str_replace('{brand}',$trip->getVehicle()->getBrand(),$contentMail);
                $contentMail = str_replace('{model}',$trip->getVehicle()->getModel(),$contentMail);
                $contentMail = str_replace('{color}',$trip->getVehicle()->getColor(),$contentMail);

                $email = (new Email())
                    ->from('noreply@ecoride.fr')
                    ->to($user->getEmail()) // Adresse email du passager
                    ->subject($mail->getSubject())
                    ->html($contentMail);


                $mailer->send($email);

                $returnMessage = [
                    "error" => false,
                    "message" => "Véhicule modifié avec succès",
                    "httpStatus" => Response::HTTP_OK
                ];
                goto retour;
            }

        }

        $returnMessage = [
            "error" => false,
            "message" => "Modifié avec succès",
            "httpStatus" => Response::HTTP_OK
        ];

        retour:

        $this->manager->flush();

        //S'il y a des participants, on ajoute le count($users) pour MongoDB
        count($users) ? $nbParticipant = count($users): $nbParticipant = 0;
        // Sérialiser les préférences de l'utilisateur pour MongoDB
        $preferencesArray = $this->serializer->normalize($user->getPreferences(), null, ['groups' => 'preferences_user']);
        //Modification dans mongoDB
        $this->tripMongoService->update($trip->getId(), [
            'id_covoiturage' => $trip->getId(),
            'status' => $trip->getStatus()?->getLibelle(),
            'startingAddress' => $trip->getStartingAddress(),
            'arrivalAddress' => $trip->getArrivalAddress(),
            'startingAt' => $trip->getStartingAt(),
            'duration' => $trip->getDuration(),
            'nbCredit' => $trip->getNbCredit(),
            'nbPlaceRemaining' => $trip->getNbPlaceRemaining(),
            'nbParticipant' => $nbParticipant,
            'user' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'preferences' => $preferencesArray,
                'grade' => $user->getGrade(),
            ],
            'vehicle' => [
                'brand' => $trip->getVehicle()?->getBrand(),
                'model' => $trip->getVehicle()?->getModel(),
                'color' => $trip->getVehicle()?->getColor(),
                'energy' => $trip->getVehicle()?->getEnergy()?->getLibelle(),
                'isEco' => $trip->getVehicle()?->getEnergy()?->isEco(),
            ],
            'createdAt' => $trip->getCreatedAt(),
            'updatedAt' => $trip->getUpdatedAt(),
        ]);

        return new JsonResponse(['error' => $returnMessage['error'], "message" => $returnMessage['message']], $returnMessage['httpStatus']);
    }

}
