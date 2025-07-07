<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AddressValidator
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function validateAndDecomposeAddress($address): array
    {
        // Si $address est un tableau, le convertir en chaîne formatée pour la recherche
        if (is_array($address)) {
            // Format attendu : "rue, code postal ville"
            $addressString = '';
            if (!empty($address['street'])) {
                $addressString .= $address['street'];
            }
            if (!empty($address['postcode'])) {
                $addressString .= (empty($addressString) ? '' : ', ') . $address['postcode'];
            }
            if (!empty($address['city'])) {
                $addressString .= (empty($addressString) ? '' : ' ') . $address['city'];
            }
        } else {
            $addressString = $address;
        }

        $response = $this->httpClient->request('GET', 'https://api-adresse.data.gouv.fr/search/', [
            'query' => [
                'q' => $addressString,
                'limit' => 1
            ]
        ]);

        try {
            $data = $response->toArray();

            if (empty($data['features'])) {
                return ['error' => 'Adresse invalide'];
            }

            $feature = $data['features'][0];
            $properties = $feature['properties'];

            // Vérifier que l'adresse est en France
            if (isset($properties['country']) && strtolower($properties['country']) !== 'france') {
                return ['error' => 'Adresse non valide : la ville doit être en France'];
            }

            // Extraire les détails de l'adresse
            $street = $properties['name'] ?? '';
            $postcode = $properties['postcode'] ?? '';
            $city = $properties['city'] ?? '';

            // Vérifier que les données retournées correspondent aux données d'entrée
            if (empty($street) || empty($city) || !str_contains(strtolower($addressString), strtolower($city))) {
                /*
                 * Si les données retournées ne correspondent pas aux données d\'entrée, c'est que l'adresse n'a pas été trouvée
                 */
                return ['error' => 'Adresse invalide'];
            }

            return [
                'street' => $street,
                'postcode' => $postcode,
                'city' => $city
            ];
        } catch (TransportExceptionInterface) {
            return ['error' => 'Erreur lors de la requête à l\'API de géocodage'];
        }
    }
}