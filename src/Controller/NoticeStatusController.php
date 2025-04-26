<?php

namespace App\Controller;

use App\Entity\NoticeStatus;
use App\Repository\NoticeStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/api/notice/status', name: 'app_api_notice_status_')]
final class NoticeStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly NoticeStatusRepository  $repository,
        private readonly SerializerInterface    $serializer,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function add(Request $request): JsonResponse
    {

        $noticeStatus = $this->serializer->deserialize($request->getContent(), NoticeStatus::class, 'json');
        $noticeStatus->setCreatedAt(new DateTimeImmutable());

        // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
        $formattedLibelle = mb_convert_case($noticeStatus->getLibelle(), MB_CASE_TITLE, "UTF-8");
        $noticeStatus->setLibelle($formattedLibelle);

        $this->manager->persist($noticeStatus);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $noticeStatus->getId(),
                'libelle'  => $noticeStatus->getLibelle(),
                'createdAt' => $noticeStatus->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list', name: 'showAll', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $noticeStatus = $this->repository->findAll();

        if ($noticeStatus) {
            $responseData = $this->serializer->serialize(
                $noticeStatus,
                'json'
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(['error' => true, 'message' => 'Aucun statut n\'a été trouvé.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function showById(int $id): JsonResponse
    {
        $noticeStatus = $this->repository->findOneBy(['id' => $id]);
        if ($noticeStatus) {
            $responseData = $this->serializer->serialize(
                $noticeStatus,
                'json'
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $noticeStatus = $this->repository->findOneBy(['id' => $id]);

        if ($noticeStatus) {
            $noticeStatus = $this->serializer->deserialize(
                $request->getContent(),
                NoticeStatus::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $noticeStatus]
            );
            // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
            $formattedLibelle = mb_convert_case($noticeStatus->getLibelle(), MB_CASE_TITLE, "UTF-8");
            $noticeStatus->setLibelle($formattedLibelle);
            $noticeStatus->setUpdatedAt(new DateTimeImmutable());

            $this->manager->flush();

            $responseData = $this->serializer->serialize(
                $noticeStatus,
                'json'
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $noticeStatus = $this->repository->findOneBy(['id' => $id]);
        if ($noticeStatus) {
            $this->manager->remove($noticeStatus);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(['error' => true, 'message' => 'Ce statut n\'existe pas.'], Response::HTTP_NOT_FOUND);
    }

}
