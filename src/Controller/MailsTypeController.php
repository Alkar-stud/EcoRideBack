<?php

namespace App\Controller;

use App\Entity\MailsType;
use App\Repository\MailsTypeRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Areas;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/ecoride/mail', name: 'app_api_ecoride_mail_')]
#[OA\Tag(name: 'MailsType')]
#[Areas(["ecoride"])]
#[IsGranted('ROLE_ADMIN')]
final class MailsTypeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path:"/api/ecoride/mail/add",
        summary:"Ajout d'un nouveau mail type",
        requestBody :new RequestBody(
            description: "Données du mail type à ajouter",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "code",
                    type: "string",
                    example: "validation"
                ),
                    new Property(
                        property: "subject",
                        type: "string",
                        example: "EcoRide - Votre compte est validé"
                    ),
                    new Property(
                        property: "content",
                        type: "string",
                        example: "Bonjour, \n votre compte est bien validé. \n EcoRide"
                    ),
                ], type: "object"))]
        ),

    )]
    #[OA\Response(
        response: 201,
        description: 'Mail type ajouté avec succès',
        content: new Model(type: MailsType::class)
    )]
    public function add(Request $request): JsonResponse
    {
        $mail = $this->serializer->deserialize($request->getContent(), MailsType::class, 'json');
        $mail->setCreatedAt(new DateTimeImmutable());
        //typemail en camelCase et ne doit contenir que des lettres
        if (!preg_match('/^[a-zA-Z]+$/', $mail->getCode())) {
            return $this->json(['error' => 'Invalid typeMail, ne doit contenir que des lettres en camelCase'], Response::HTTP_BAD_REQUEST);
        }
        //Sécurisation des données HTML
        if ($request->getPayload()->get('subject'))
        {
            $mail->setContent(htmlspecialchars($request->getPayload()->get('subject'), ENT_QUOTES, 'UTF-8'));
        }
        if ($request->getPayload()->get('content'))
        {
            $mail->setContent(htmlspecialchars($request->getPayload()->get('content'), ENT_QUOTES, 'UTF-8'));
        }

        $this->manager->persist($mail);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $mail->getId(),
                'typeMail'  => $mail->getCode(),
                'subject' => $mail->getSubject(),
                'content' => $mail->getContent(),
                'createdAt' => $mail->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );

    }

    #[Route('/list', name: 'showAll', methods: ['GET'])]
    #[Areas(["ecoride"])]
    #[OA\Get(
        path:"/api/ecoride/mail/list",
        summary:"Récupérer tous les mails type.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Mail(s) type trouvé(s) avec succès',
        content: new Model(type: MailsType::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    public function showAll(MailsTypeRepository $mailsTypeRepository): JsonResponse
    {
        $mails = $mailsTypeRepository->findAll();

        return $this->json($mails);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[Areas(["ecoride"])]
    #[OA\Get(
        path:"/api/ecoride/mail/{id}",
        summary:"Récupérer un mail type avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Mail type trouvé avec succès',
        content: new Model(type: MailsType::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'Mail non trouvé'
    )]
    public function show(int $id, MailsTypeRepository $mailsTypeRepository): JsonResponse
    {
        $mail = $mailsTypeRepository->find($id);

        if (!$mail) {
            return $this->json(['error' => 'Mail not found'], 404);
        }

        return $this->json($mail);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ecoride/mail/{id}",
        summary:"Modification d'un mail type",
        requestBody :new RequestBody(
            description: "Données du mail type à modifier",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "subject",
                    type: "string",
                    example: "EcoRide - Votre compte est validé"
                ),
                    new Property(
                        property: "content",
                        type: "string",
                        example: "Bonjour, \n votre compte est bien validé. \n EcoRide"
                    ),
                ], type: "object"))]
        ),

    )]
    #[OA\Response(
        response: 201,
        description: 'Mail type modifier avec succès',
        content: new Model(type: MailsType::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'Mail type non trouvé'
    )]
    public function update(int $id, Request $request, MailsTypeRepository $mailsTypeRepository): JsonResponse
    {
        $mailType = $mailsTypeRepository->find($id);

        if (!$mailType) {
            return $this->json(['error' => 'Mail not found'], 404);
        }
        $originalCode = $mailType->getCode();

        //Sécurisation des données HTML
        if ($request->getPayload()->get('subject'))
        {
            $mailType->setSubject(htmlspecialchars($request->getPayload()->get('subject'), ENT_QUOTES, 'UTF-8'));
        }
        if ($request->getPayload()->get('content'))
        {
            $mailType->setContent(htmlspecialchars($request->getPayload()->get('content'), ENT_QUOTES, 'UTF-8'));
        }

        //On remet le code initial, car étant présent en dur dans le code, il ne doit pas être changé
        $mailType->setCode($originalCode);
        $mailType->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return $this->json($mailType);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path:"/api/ecoride/mail/{id}",
        summary:"Supprimer un mail type - à n'utiliser que si vous êtes sûr de savoir ce que vous faites.",
    )]
    #[OA\Response(
        response: 204,
        description: 'Mail type supprimé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Mail type non trouvé'
    )]
    public function delete(int $id, MailsTypeRepository $mailsTypeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $mail = $mailsTypeRepository->find($id);

        if (!$mail) {
            return $this->json(['error' => 'Mail type non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($mail);
        $entityManager->flush();

        return $this->json(['message' => 'Mail type supprimé avec succès'], Response::HTTP_NO_CONTENT);
    }

}
