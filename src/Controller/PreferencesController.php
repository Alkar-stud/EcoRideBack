<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PreferencesService;
use Nelmio\ApiDocBundle\Attribute\Areas;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/account/preferences', name: 'app_api_account_preferences_')]
#[OA\Tag(name: 'User/Preferences')]
#[Areas(["default"])]
final class PreferencesController extends AbstractController
{
    public function __construct(
        private readonly PreferencesService $preferencesService
    ) {}

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path: "/api/account/preferences/add",
        summary: "Ajout d'une préférence à l'utilisateur connecté",
        requestBody: new RequestBody(
            description: "Données de la préférence à inscrire",
            required: true,
            content: [new MediaType(mediaType: "application/json",
                schema: new Schema(properties: [
                    new Property(property: "libelle", type: "string", example: "Ma préférence"),
                    new Property(property: "description", type: "text", example: "J'écoute du métal")
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Préférence ajoutée avec succès'
    )]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $result = $this->preferencesService->addPreference($user, $request);

        return new JsonResponse($result, Response::HTTP_CREATED);
    }

    #[Route('/list/', name: 'showAll', methods: 'GET')]
    #[OA\Get(
        path: "/api/account/preferences/list/",
        summary: "Récupérer toutes les préférences du User connecté",
    )]
    #[OA\Response(
        response: 200,
        description: 'Préférences trouvées avec succès'
    )]
    public function showAll(#[CurrentUser] ?User $user): JsonResponse
    {
        $preferences = $this->preferencesService->getAllPreferences($user);

        if ($preferences) {
            return new JsonResponse($preferences, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: "/api/account/preferences/{id}",
        summary: "Modifier une préférence du User connecté",
        requestBody: new RequestBody(
            description: "Données de l'utilisateur à modifier",
            content: [new MediaType(mediaType: "application/json",
                schema: new Schema(properties: [
                    new Property(property: "libelle", type: "string", example: "Nouveau libellé"),
                    new Property(property: "description", type: "string", example: "Nouvelle description")
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Préférence modifiée avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Préférence non trouvée'
    )]
    public function edit(#[CurrentUser] ?User $user, int $id, Request $request): JsonResponse
    {
        $result = $this->preferencesService->editPreference($user, $id, $request);

        if (isset($result['error']) && $result['error']) {
            return new JsonResponse($result, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: "/api/account/preferences/{id}",
        summary: "Supprimer une préférence",
    )]
    #[OA\Response(
        response: 204,
        description: 'Préférence supprimée avec succès'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cette préférence ne peut pas être supprimée.'
    )]
    #[OA\Response(
        response: 404,
        description: 'Préférence non trouvée'
    )]
    public function delete(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $result = $this->preferencesService->deletePreference($user, $id);

        if (isset($result['message'])) {
            return new JsonResponse($result, $result['status']);
        }

        return new JsonResponse(['success' => true], Response::HTTP_OK);
    }
}
