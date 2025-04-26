<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Repository\MailRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/mail', name: 'app_api_mail_')]
final class MailController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $mail = $this->serializer->deserialize($request->getContent(), Mail::class, 'json');
        $mail->setCreatedAt(new DateTimeImmutable());

        //Sécurisation des données HTML
        $request->getPayload()->get('subject') ? $mail->setContent(htmlspecialchars($request->getPayload()->get('subject'), ENT_QUOTES, 'UTF-8')): '';
        $request->getPayload()->get('content') ? $mail->setContent(htmlspecialchars($request->getPayload()->get('content'), ENT_QUOTES, 'UTF-8')): '';

        $this->manager->persist($mail);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $mail->getId(),
                'typeMail'  => $mail->getTypeMail(),
                'subject' => $mail->getSubject(),
                'content' => $mail->getContent(),
                'createdAt' => $mail->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    public function update(int $id, Request $request, MailRepository $mailRepository): JsonResponse
    {
        $mail = $mailRepository->find($id);

        if (!$mail) {
            return $this->json(['error' => 'Mail not found'], 404);
        }

        $request->getPayload()->get('subject') ? $mail->setContent(htmlspecialchars($request->getPayload()->get('subject'), ENT_QUOTES, 'UTF-8')): '';
        $request->getPayload()->get('content') ? $mail->setContent(htmlspecialchars($request->getPayload()->get('content'), ENT_QUOTES, 'UTF-8')): '';
        $mail->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return $this->json($mail);
    }

    #[Route('/list/', name: 'showAll', methods: ['GET'])]
    public function index(MailRepository $mailRepository): JsonResponse
    {
        $mails = $mailRepository->findAll();

        return $this->json($mails);
    }


    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, MailRepository $mailRepository): JsonResponse
    {
        $mail = $mailRepository->find($id);

        if (!$mail) {
            return $this->json(['error' => 'Mail not found'], 404);
        }

        return $this->json($mail);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, MailRepository $mailRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $mail = $mailRepository->find($id);

        if (!$mail) {
            return $this->json(['error' => 'Mail not found'], 404);
        }

        $entityManager->remove($mail);
        $entityManager->flush();

        return $this->json(['message' => 'Mail deleted successfully']);
    }

}
