<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Config;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;

/**
 * Reads the site-wide schema settings that live on the root page (schema_*
 * fields added via DCA) and normalises them for the node providers.
 */
final class SchemaConfig
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function isDisabled(PageModel $rootPage): bool
    {
        return (bool) $rootPage->schema_disable;
    }

    /** Organization | LocalBusiness | '' (=> Organization). Empty "none" hides it. */
    public function organizationType(PageModel $rootPage): string
    {
        $type = trim((string) $rootPage->schema_orgType);

        return $type !== '' ? $type : 'Organization';
    }

    public function showsOrganization(PageModel $rootPage): bool
    {
        return (string) $rootPage->schema_orgType !== 'none';
    }

    public function organizationName(PageModel $rootPage): string
    {
        $name = trim((string) $rootPage->schema_orgName);

        return $name !== '' ? $name : trim((string) $rootPage->title);
    }

    /**
     * @return list<string>
     */
    public function sameAs(PageModel $rootPage): array
    {
        $values = StringUtil::deserialize($rootPage->schema_orgSameAs, true);

        return array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $values),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * @return list<string> e.g. ["Mo-Fr 09:00-18:00", "Sa 10:00-14:00"]
     */
    public function openingHours(PageModel $rootPage): array
    {
        $values = StringUtil::deserialize($rootPage->schema_openingHours, true);

        return array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $values),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * PostalAddress node, or null when no address parts are set.
     *
     * @return array<string, mixed>|null
     */
    public function postalAddress(PageModel $rootPage): ?array
    {
        $map = [
            'streetAddress' => (string) $rootPage->schema_orgStreet,
            'postalCode' => (string) $rootPage->schema_orgPostal,
            'addressLocality' => (string) $rootPage->schema_orgCity,
            'addressRegion' => (string) $rootPage->schema_orgRegion,
            'addressCountry' => (string) $rootPage->schema_orgCountry,
        ];

        $address = ['@type' => 'PostalAddress'];
        foreach ($map as $key => $value) {
            $value = trim($value);
            if ($value !== '') {
                $address[$key] = $value;
            }
        }

        return \count($address) > 1 ? $address : null;
    }

    /**
     * GeoCoordinates node, or null when lat/lng are missing.
     *
     * @return array<string, mixed>|null
     */
    public function geo(PageModel $rootPage): ?array
    {
        $lat = trim((string) $rootPage->schema_geoLat);
        $lng = trim((string) $rootPage->schema_geoLng);

        if ($lat === '' || $lng === '') {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    public function telephone(PageModel $rootPage): string
    {
        return trim((string) $rootPage->schema_orgPhone);
    }

    public function email(PageModel $rootPage): string
    {
        return trim((string) $rootPage->schema_orgEmail);
    }

    public function priceRange(PageModel $rootPage): string
    {
        return trim((string) $rootPage->schema_priceRange);
    }

    public function showsWebSite(PageModel $rootPage): bool
    {
        return (bool) $rootPage->schema_website;
    }

    public function searchUrlTemplate(PageModel $rootPage): string
    {
        return trim((string) $rootPage->schema_searchUrl);
    }

    /**
     * Absolute URL of the configured logo file, or '' when unset/missing.
     */
    public function logoUrl(PageModel $rootPage, string $baseUrl): string
    {
        return $this->fileUrl($rootPage->schema_orgLogo, $baseUrl);
    }

    /**
     * Resolve a binary file UUID to an absolute URL ('' when unset/missing).
     *
     * @param mixed $uuid binary UUID as stored by Contao file pickers
     */
    public function fileUrl($uuid, string $baseUrl): string
    {
        if (empty($uuid)) {
            return '';
        }

        /** @var FilesModel $filesAdapter */
        $filesAdapter = $this->framework->getAdapter(FilesModel::class);
        $file = $filesAdapter->findByUuid($uuid);

        if ($file === null || (string) $file->path === '') {
            return '';
        }

        return $baseUrl . '/' . ltrim((string) $file->path, '/');
    }
}
