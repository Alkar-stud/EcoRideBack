<?php

namespace App\Controller;

use App\Entity\Preferences;
use App\Entity\User;
use App\Service\MailService;
use App\Repository\EcorideRepository;
use App\Service\MongoService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GdImage;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Attribute\Areas;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Schema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


#[Route('/api', name: 'app_api_')]
#[OA\Tag(name: 'User')]
#[Areas(["default"])]
class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface      $manager,
        private readonly SerializerInterface         $serializer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailService                 $mailService,
        private readonly EcorideRepository           $ecorideRepository,
        private readonly MongoService                $mongoService,
    )
    {
    }


    #[Route('/registration', name: 'registration', methods: 'POST')]
    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/registration', name: 'registration', methods: 'POST')]
    #[OA\Post(
        path:"/api/registration",
        summary:"Inscription d'un nouvel utilisateur",
        requestBody :new RequestBody(
            description: "Données de l'utilisateur à inscrire",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "pseudo",
                    type: "string",
                    example: "Pseudo"
                ),
                    new Property(
                        property: "email",
                        type: "string",
                        example: "adresse@email.com"
                    ),
                    new Property(
                        property: "password",
                        type: "string",
                        example: "M0t de passe"
                    )], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur inscrit avec succès'
    )]
    #[OA\Response(
        response: 400,
        description: 'Le mot de passe doit contenir au moins 10 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial. Ou il manque le pseudo'
    )]
    #[OA\Response(
        response: 409,
        description: 'Ce compte existe déjà'
    )]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');

        //Vérification que les champs sont tous renseignés
        if (null === $user->getPseudo() || null === $user->getPassword() || null === $user->getEmail()) {
            return new JsonResponse(['error' => true, 'message' => 'Informations incomplètes'], Response::HTTP_BAD_REQUEST);
        }

        //Vérification de l'existence de l'utilisateur pour ne pas avoir d'email en double
        $existingUser = $this->manager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return new JsonResponse(['error' => true, 'message' => 'Ce compte existe déjà'], Response::HTTP_CONFLICT);
        }

        //Validation de la complexité du mot de passe
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $user->getPassword())) {
            return new JsonResponse(['message' => 'Le mot de passe doit contenir au moins 10 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial.'], Response::HTTP_BAD_REQUEST);
        }

        // Ajout des crédits de bienvenue s'il y en a
        $ecorideTotalCredit   = $this->ecorideRepository->findOneByLibelle('TOTAL_CREDIT');
        $ecorideWelcomeCredit = $this->ecorideRepository->findOneByLibelle('WELCOME_CREDIT');

        //Mise à jour du crédit total
        $ecorideTotalCredit->setParameterValue($ecorideTotalCredit->getParameterValue() - $ecorideWelcomeCredit->getParameterValue());

        $user->setCredits($ecorideWelcomeCredit->getParameterValue());


        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));
        $user->setCreatedAt(new DateTimeImmutable());

        //Ajout des 2 préférences par défaut à "no" pour fumeur et animaux
        $smokingPreference = new Preferences();
        $smokingPreference->setLibelle('smokingAllowed');
        $smokingPreference->setDescription('no');
        $smokingPreference->setUser($user);
        $smokingPreference->setCreatedAt(new DateTimeImmutable());

        $petsPreference = new Preferences();
        $petsPreference->setLibelle('petsAllowed');
        $petsPreference->setDescription('no');
        $petsPreference->setUser($user);
        $petsPreference->setCreatedAt(new DateTimeImmutable());

        $user->addUserPreference($smokingPreference);
        $user->addUserPreference($petsPreference);
        //persister les préférences
        $this->manager->persist($smokingPreference);
        $this->manager->persist($petsPreference);

        //persister l'utilisateur
        $this->manager->persist($user);
        $this->manager->flush();

        //Mise à jour des mouvements de crédits sur EcoRide
        $this->mongoService->addMovementCreditsForRegistration($ecorideWelcomeCredit->getParameterValue(), $user, 'registrationUser');

        //On envoie le mail type 'accountUserCreate' à l'utilisateur
        $this->mailService->sendEmail($user->getEmail(), 'accountUserCreate', ['pseudo' => $user->getPseudo()]);


        return new JsonResponse(
            ['message' => 'Utilisateur inscrit avec succès', 'user' => $user->getUserIdentifier()],
            Response::HTTP_CREATED
        );
    }

    #[Route('/login', name: 'login', methods: 'POST')]
    #[OA\Post(
        path:"/api/login",
        summary:"Connecter un utilisateur",
        requestBody :new RequestBody(
            description: "Données de l’utilisateur pour se connecter",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "email",
                    type: "string",
                    example: "adresse@email.com"
                ),
                    new Property(
                        property: "password",
                        type: "string",
                        example: "M0t de passe"
                    )], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: "Connexion réussie",
        content: new Model(type: User::class, groups: ['user_login'])
    )]
    #[OA\Response(
        response: 401,
        description: "Erreur dans les identifiants"
    )]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user'  => $user->getUserIdentifier(),
            'apiToken' => $user->getApiToken(),
            'roles' => $user->getRoles(),
        ]);
    }


    #[Route('/account/me', name: 'account_me', methods: 'GET')]
    #[OA\Get(
        path:"/api/account/me",
        summary:"Récupérer toutes les informations du User connecté",
    )]
    #[OA\Response(
        response: 200,
        description: 'User trouvé avec succès',
        content: new Model(type: User::class, groups: ['user_read', 'vehicle_read'])
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (null === $user) {
            return new JsonResponse(['error' => true, 'message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            ['groups' => ['user_account']]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/account/edit', name: 'account_edit', methods: 'PUT')]
    #[OA\Put(
        path:"/api/account/edit",
        summary:"Modifier son compte utilisateur",
        requestBody :new RequestBody(
            description: "Données de l'utilisateur à modifier",
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "pseudo",
                    type: "string",
                    example: "Nouveau pseudo"
                ),
                    new Property(
                        property: "photo",
                        type: "string",
                        example: "Nouvelle photo (jpg, png ou webp, max 100px*100px)"
                    ),
                    new Property(
                        property: "isDriver",
                        type: "boolean",
                        example: true
                    ),
                    new Property(
                        property: "isPassenger",
                        type: "boolean",
                        example: true
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'User modifié avec succès',
        content: new Model(type: User::class, groups: ['user_read'])
    )]
    #[OA\Response(
        response: 400,
        description: 'Erreur dans l\'envoi des données'
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function edit(
        #[CurrentUser] ?User $user,
        Request $request,
        #[Autowire('%kernel.project_dir%/public/uploads/photos')] string $photoDirectory
    ): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $originalEmail = $user->getEmail();
        $originalRoles = $user->getRoles();
        $originalIsActive = $user->isActive();
        $originalCredits = $user->getCredits();
        $originalGrade = $user->getGrade();

        $user = $this->serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $user]
        );
        // Empêcher la modification des rôles par le User
        $user->setRoles($originalRoles);
        // Empêcher la modification de isActive par le User
        $user->setIsActive($originalIsActive);
        // Empêcher la modification de l'email par le User
        $user->setEmail($originalEmail);
        // Empêcher la modification de la note par le user
        $user->setGrade($originalGrade);

        //Si deletePhoto === true, on supprime le fichier $user->getPhoto() dans uploads/photo et $user->setPhoto() = null;
        // Récupération des données de la requête.
        $data = json_decode($request->getContent(), true);

        // Vérification si deletePhoto est présent dans la requête et est true
        if (isset($data['deletePhoto']) && $data['deletePhoto'] === true) {
            $oldPhotoPath = $photoDirectory . '/' . $user->getPhoto();
            if ($user->getPhoto() && file_exists($oldPhotoPath) && is_writable($oldPhotoPath)) {
                @unlink($oldPhotoPath);
                $user->setPhoto(null);
            }
        }

        if (isset($request->toArray()['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
        }

        $user->setUpdatedAt(new DateTimeImmutable());

        // Vérifier si l'utilisateur a le rôle ROLE_ADD_CREDIT avant de modifier les crédits
        if (!$this->isGranted('ROLE_ADD_CREDIT')) {
            $user->setCredits($originalCredits);
        }
        //Vérification de l'intégrité de l'upload de la photo et on lui met comme nom un slug.


        $user->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            ['groups' => ['user_account']]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    /**
     * @throws RandomException
     */
    #[Route('/account/upload', name: 'account_upload', methods: 'POST')]
    #[OA\Post(
        path:"/api/account/upload",
        summary:"Envoyer la photo de profil",
        requestBody :new RequestBody(
            description: "Photo de l'utilisateur à modifier",
            content: [new MediaType(mediaType:"multipart/form-data",
                schema: new Schema(properties: [new Property(
                    property: "photo",
                    description: "Fichier image à uploader (jpg, png ou webp, max 100px*100px)",
                    type: "string",
                    format: "binary"
                )], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Image envoyé avec succès',
        content: new Model(type: User::class, groups: ['user_read'])
    )]
    #[OA\Response(
        response: 400,
        description: 'Erreur dans l\'envoi des données'
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function upload(
        #[CurrentUser] ?User $user,
        Request $request,
        #[Autowire('%kernel.project_dir%/public/uploads/photos')] string $photoDirectory
    ): JsonResponse {
        if ($user === null) {
            return new JsonResponse(['error' => true, 'message' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$request->files->has('photo')) {
            return new JsonResponse(['error' => true, 'message' => 'Aucun fichier photo trouvé'], Response::HTTP_BAD_REQUEST);
        }

        $photoFile = $request->files->get('photo');
        $newFilename = bin2hex(random_bytes(16)) . '.' . $photoFile->guessExtension();

        // Vérification de l'extension
        $allowedExtensions = ["jpeg", "jpg", "png", "webp"];
        if (!in_array($photoFile->guessExtension(), $allowedExtensions)) {
            return new JsonResponse(['error' => true, 'message' => 'L\'extension de la photo est incorrecte'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification de la taille
        if ($photoFile->getSize() > 2000000) {
            return new JsonResponse(['error' => true, 'message' => 'La photo est trop lourde'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification des dimensions
        $image = getimagesize($photoFile->getPathname());
        if ($image[0] > 100 || $image[1] > 100) {
            try {
                // On redimensionne la photo
                $resizedImage = $this->resizeAvatar($photoFile);
                $targetPath = $photoDirectory . '/' . $newFilename;

                // Enregistrer l'image redimensionnée
                switch ($photoFile->getMimeType()) {
                    case 'image/jpeg':
                        if (!@imagejpeg($resizedImage, $targetPath)) {
                            throw new Exception("Impossible d'écrire l'image JPEG");
                        }
                        break;
                    case 'image/png':
                        if (!@imagepng($resizedImage, $targetPath)) {
                            throw new Exception("Impossible d'écrire l'image PNG");
                        }
                        break;
                    case 'image/webp':
                        if (!@imagewebp($resizedImage, $targetPath)) {
                            throw new Exception("Impossible d'écrire l'image WebP");
                        }
                        break;
                    default:
                        throw new Exception("Format d'image non pris en charge");
                }

                imagedestroy($resizedImage);

                if ($user->getPhoto()) {
                    $oldPhotoPath = $photoDirectory . '/' . $user->getPhoto();
                    if (file_exists($oldPhotoPath) && is_writable($oldPhotoPath)) {
                        @unlink($oldPhotoPath);
                    }
                }
                // On met à jour l'utilisateur avec le nouveau nom de fichier
                $user->setPhoto($newFilename);
                $this->manager->flush();
                return new JsonResponse(['success' => true, 'message' => 'Photo redimensionnée et uploadée avec succès'], Response::HTTP_OK);
            } catch (Exception $e) {
                return new JsonResponse(['error' => true, 'message' => 'Erreur lors du redimensionnement de l\'image: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Déplacement de la nouvelle photo s'il n'y a pas de redimensionnement à faire
        try {
            $photoFile->move($photoDirectory, $newFilename);
            $user->setPhoto($newFilename);
            $this->manager->flush();
        } catch (FileException $e) {
            return new JsonResponse(['message' => 'Erreur lors de l\'upload de la photo: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => 'Photo uploadée avec succès'], Response::HTTP_OK);
    }

    private function resizeAvatar(mixed $photoFile): GdImage
    {
        // Vérification des dimensions de l'image
        $imageInfo = getimagesize($photoFile->getPathname());
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];

        // Calcul des nouvelles dimensions en gardant le ratio
        $newHeight = 100;
        $newWidth = intval(($originalWidth / $originalHeight) * $newHeight);

        // Chargement de l'image source
        $source = match ($photoFile->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($photoFile->getPathname()),
            'image/png' => imagecreatefrompng($photoFile->getPathname()),
            'image/webp' => imagecreatefromwebp($photoFile->getPathname()),
            default => throw new InvalidArgumentException('Format d\'image non supporté'),
        };

        // Création de l'image redimensionnée
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Préservation de la transparence pour les PNG
        if ($photoFile->getMimeType() === 'image/png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }

        imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Libération de la mémoire de l'image source
        imagedestroy($source);

        return $resizedImage;
    }

    #[Route('/account', name: 'account_delete', methods: 'DELETE')]
    #[OA\Delete(
        path:"/api/account",
        summary:"Supprimer son compte utilisateur",
    )]
    #[OA\Response(
        response: 204,
        description: 'User supprimé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function delete(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user) {
            //On supprime le fichier de l'image de profil s'il y en a une
            if ($user->getPhoto()) {
                $photoDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/photos';
                $oldPhotoPath = $photoDirectory . '/' . $user->getPhoto();
                if (file_exists($oldPhotoPath) && is_writable($oldPhotoPath)) {
                    @unlink($oldPhotoPath);
                }
            }

            // On supprime l'utilisateur
            $this->manager->remove($user);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


}
