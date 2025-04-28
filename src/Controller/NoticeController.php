<?php

namespace App\Controller;

use App\Entity\Notice;
use App\Entity\NoticeStatus;
use App\Entity\Trip;
use App\Entity\User;
use App\Repository\NoticeRepository;
use DateTimeImmutable;
use App\Service\NoticeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/notice', name: 'app_api_notice_')]
final class NoticeController extends AbstractController{

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly NoticeRepository       $repository,
        private readonly NoticeService          $noticeService,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    /**
     * @property SerializerInterface&Serializer $serializer
     */
    #[Route('/add/{tripID}', name: 'add', methods: ['POST'])]
    public function add(#[CurrentUser] ?User $user, Request $request, int $tripID): JsonResponse
    {
        // Recherche du covoiturage correspondant à l'ID
        $trip = $this->manager->getRepository(Trip::class)->find($tripID);
        if (!$trip) {
            return new JsonResponse(['error' => 'Ce covoiturage n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }

        // Vérification si l'utilisateur est un participant
        $participants = $trip->getUser();
        if (!$participants->contains($user)) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas un participant de ce covoiturage'], Response::HTTP_UNAUTHORIZED);
        }

        // Recherche de l'entité NoticeStatus avec l'ID par défaut
        $defaultNoticeStatusId = intval($this->noticeService->getDefaultStatus()) ?? 1;
        $defaultNoticeStatus = $this->manager->getRepository(NoticeStatus::class)->find($defaultNoticeStatusId);
        if (!$defaultNoticeStatus) {
            return new JsonResponse(['error' => true, 'message' => 'Default status not found'], Response::HTTP_NOT_FOUND);
        }
        //Il y a déjà un avis sur ce covoiturage du user ?
        $userNoticeForThisTrip = $this->manager->getRepository(Notice::class)->findBy(['relatedFor' => $trip, 'publishedBy' => $user]);
        //S'il existe déjà un avis de ce user sur ce voyage, on vérifie si l'avais est toujours en état "Déposé", c'est-à-dire le statut initial, on autorise la modification de l'avis et de la note.
        //Sinon, FORBIDDEN
        if (!empty($userNoticeForThisTrip)) {
            $userNotice = $userNoticeForThisTrip[0]; // Récupère le premier élément
            $status = $userNotice->getStatus(); // Récupère le statut
            //Si le statut est différent du statut initial
            if ($status != $defaultNoticeStatus) {
                return new JsonResponse(['error' => true, 'message' => 'Vous avez déjà déposé un avis et celui-ci n\'est plus modifiable.'], Response::HTTP_FORBIDDEN);
            } else {
                return new JsonResponse(['error' => true, 'message' => 'Vous avez déjà déposé un avis et celui-ci est encore modifiable.'], Response::HTTP_BAD_REQUEST);
            }
        }
        //Si rien de trouvé, on accepte

        $notice = $this->serializer->deserialize($request->getContent(), Notice::class, 'json');


        // Attribution du statut par défaut au covoiturage
        $notice->setStatus($defaultNoticeStatus);
        $notice->setRelatedFor($trip);
        $notice->setPublishedBy($user);
        $notice->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($notice);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $notice->getId(),
                'status'  => [
                    'libelle' => $notice->getStatus()?->getLibelle()
                ],
                'title'  => $notice->getTitle(),
                'content'  => $notice->getContent(),
                'createdAt' => $notice->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list/{tripID}', name: 'showAll', methods: 'GET')]
    public function showAll(int $tripID): JsonResponse
    {
        // Recherche du voyage correspondant
        $trip = $this->manager->getRepository(Trip::class)->find($tripID);
        if (!$trip) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }

        // Récupération des avis liés au voyage
        $notices = $this->repository->findBy(['relatedFor' => $trip]);

        // Vérification si des notices existent
        if (empty($notices)) {
            return new JsonResponse(['message' => 'Aucun avis trouvé pour ce covoiturage.'], Response::HTTP_NOT_FOUND);
        }

        // Sérialisation des données
        $data = $this->serializer->serialize($notices, 'json', ['groups' => ['notice_read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        // Recherche de l'avis correspondant
        $notice = $this->repository->findOneBy(['id' => $id]);

        // Vérification si des avis existent
        if (empty($notice)) {
            return new JsonResponse(['message' => 'Cet avis n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }

        // Sérialisation des données
        $data = $this->serializer->serialize($notice, 'json', ['groups' => ['notice_read', 'notice_detail']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(#[CurrentUser] ?User $user, Request $request, int $id): JsonResponse
    {
        // Recherche de l'avis correspondant dont seul le propriétaire peut modifier
        $notice = $this->repository->findOneBy(['id' => $id, 'publishedBy' => $user]);
        if (empty($notice)) {
            return new JsonResponse(['message' => 'Cet avis n\'existe pas ou vous n\'avez pas le droit de le modifier.'], Response::HTTP_BAD_REQUEST);
        }
        // Recherche de l'entité NoticeStatus avec l'ID par défaut
        $defaultNoticeStatusId = intval($this->noticeService->getDefaultStatus()) ?? 1;
        $defaultNoticeStatus = $this->manager->getRepository(NoticeStatus::class)->find($defaultNoticeStatusId);
        if (!$defaultNoticeStatus) {
            return new JsonResponse(['error' => true, 'message' => 'Default status not found'], Response::HTTP_NOT_FOUND);
        }
        //Si l'avis est à l'état initial
        if ($notice->getStatus()->getId() != $defaultNoticeStatusId)
        {
            return new JsonResponse(['message' => 'Cet avis n\'est plus modifiable.'], Response::HTTP_FORBIDDEN);
        }
        $noticeStatus = $this->serializer->deserialize(
            $request->getContent(),
            Notice::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $notice]
        );
        $notice->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        $responseData = $this->serializer->serialize(
            $notice,
            'json',
            ['groups' => ['notice_read']]
        );
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/changeStatus/{id}', name: 'change_status', methods: 'PUT')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function changeStatus(#[CurrentUser] ?User $user, Request $request, int $id): JsonResponse
    {
        // Recherche de l'avis correspondant dont seul le propriétaire peut modifier
        $notice = $this->repository->findOneBy(['id' => $id]);
        if (empty($notice)) {
            return new JsonResponse(['message' => 'Cet avis n\'existe pas ou vous n\'avez pas le droit de le modifier.'], Response::HTTP_BAD_REQUEST);
        }
        //Vérification de l'existence du statut
        $content = json_decode($request->getContent(), true); // Décoder en tableau associatif
        $statusId = $content['status'] ?? null;
        $noticeStatus = $this->manager->getRepository(NoticeStatus::class)->find($statusId);
        $noticesStatus = $this->manager->getRepository(NoticeStatus::class)->findAll();

        $isStatusFound = array_filter(
            $noticesStatus,
            fn(NoticeStatus $status) => $status->getId() === $noticeStatus?->getId()
        );

        if (empty($isStatusFound)) {
            return new JsonResponse(['message' => 'Ce statut n\'existe pas.'], Response::HTTP_BAD_REQUEST);
        }

        //Modification du statut de l'avis
        $notice->setStatus($noticeStatus);
        $notice->setValidateBy($user);
        $notice->setValidateAt(new DateTimeImmutable());
        $notice->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $notice->getId(),
                'status'  => [
                    'libelle' => $notice->getStatus()?->getLibelle()
                ],
                'title'  => $notice->getTitle(),
                'validateBy'  => $notice->getValidateBy()->getPseudo(),
                'validateAt'  => $notice->getValidateAt(),
            ],
            Response::HTTP_CREATED
        );
    }

}
