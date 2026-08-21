<?php

declare(strict_types=1);

/*
 * Schema.org Structured Data
 *
 * Package: vtinnovations/schema-org
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTinnovations\SchemaOrg\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Intake\RequestAuthorization;
use VTinnovations\SchemaOrg\Operation\PushIntake;

/**
 * The public endpoint the issuer pushes replacement packages to.
 *
 * Deliberately thin. It bounds the request — method, media type, size — copies
 * the five signed headers across and hands everything to the operation. No key
 * material, no signature logic and no storage decisions live here, and nothing
 * it does can write a path or produce executable code.
 *
 * It sits outside the backend login because the caller is a server, not a
 * person; authentication is cryptographic instead.
 */
final class PackageIntakeController
{
    private const MAX_BODY_BYTES = 262144;

    public function __construct(
        private readonly PushIntake $intake,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(
                ['status' => 'method_not_allowed'],
                Response::HTTP_METHOD_NOT_ALLOWED,
                ['Allow' => 'POST'],
            );
        }

        $mediaType = strtolower(trim((string) $request->headers->get('Content-Type', '')));

        if (!str_starts_with($mediaType, 'application/json')) {
            return $this->generic(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'unsupported_media_type');
        }

        $declared = (int) $request->headers->get('Content-Length', '0');

        if ($declared > self::MAX_BODY_BYTES) {
            return $this->generic(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'payload_too_large');
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BODY_BYTES) {
            return $this->generic(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'payload_too_large');
        }

        $headers = [];

        foreach ([
            RequestAuthorization::HEADER_REQUEST_ID,
            RequestAuthorization::HEADER_TIMESTAMP,
            RequestAuthorization::HEADER_NONCE,
            RequestAuthorization::HEADER_KEY_ID,
            RequestAuthorization::HEADER_SIGNATURE,
        ] as $name) {
            $headers[$name] = (string) $request->headers->get($name, '');
        }

        try {
            $result = $this->intake->handle(
                'POST',
                $request->getBaseUrl() . $request->getPathInfo(),
                $body,
                $headers,
                time(),
            );
        } catch (PackageRejected $rejected) {
            return $this->refuse($rejected);
        } catch (\Throwable) {
            // Never echo an internal message: it can carry paths or packet data.
            $this->logger->warning('schema-org package push failed', ['operation' => 'push', 'result' => 'error']);

            return $this->generic(Response::HTTP_INTERNAL_SERVER_ERROR, 'unavailable');
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }

    private function refuse(PackageRejected $rejected): JsonResponse
    {
        $authenticationFailed = \in_array($rejected->category(), [
            PackageRejected::REQUEST_UNAUTHENTICATED,
            PackageRejected::REQUEST_MALFORMED,
            PackageRejected::REQUEST_STALE,
            PackageRejected::ANCHOR_STORE_EMPTY,
            PackageRejected::ANCHOR_UNKNOWN,
            PackageRejected::ALGORITHM_UNSUPPORTED,
        ], true);

        // The category is recorded internally; the caller only learns that the
        // request was refused.
        $this->logger->warning('schema-org package push refused', [
            'operation' => 'push',
            'result' => $rejected->category(),
        ]);

        return $this->generic(
            $authenticationFailed ? Response::HTTP_UNAUTHORIZED : Response::HTTP_FORBIDDEN,
            'rejected',
        );
    }

    private function generic(int $status, string $token): JsonResponse
    {
        return new JsonResponse(['status' => $token], $status);
    }
}
