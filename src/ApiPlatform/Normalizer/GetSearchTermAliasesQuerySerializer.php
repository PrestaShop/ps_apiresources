<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\APIResources\ApiPlatform\Normalizer;

use PrestaShop\PrestaShop\Core\Domain\Alias\Query\GetAliasesBySearchTermForEditing;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class GetSearchTermAliasesQuerySerializer implements DenormalizerInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        // We need to get the new search term from the request body if exists then from the data.
        // This is because the search term in the URL is used for filtering the aliases, but if we want to update
        // the search term of the aliases to the new one provided in the request body, we need to use the new search
        // term instead of the one in the URL.
        $request = $this->requestStack->getCurrentRequest();
        $body = json_decode($request->getContent(), true);
        $searchTerm = $body['searchTerm'] ?? $data['searchTerm'] ?? '';
        // For update operation with a new search term
        if (!empty($body['newSearchTerm'])) {
            $searchTerm = $body['newSearchTerm'];
        }

        return new GetAliasesBySearchTermForEditing($searchTerm);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null)
    {
        return $type === GetAliasesBySearchTermForEditing::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            GetAliasesBySearchTermForEditing::class => true,
            'object' => null,
            '*' => null,
        ];
    }
}
