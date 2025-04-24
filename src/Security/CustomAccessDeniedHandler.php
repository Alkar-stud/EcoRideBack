<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CustomAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): JsonResponse
    {
        $data = [
            'message' => 'Vous n\'avez pas les permissions nécessaires pour accéder à cette ressource.'
        ];

        return new JsonResponse($data, 403);
    }
}