<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Laravel 11 API",
    version: "1.0.0",
    description: "API Documentation Laravel 11"
)]

#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]

/* =====================
 | AUTH
 ===================== */

#[OA\PathItem(
    path: "/api/login",
    post: new OA\Post(
        operationId: "auth_login",
        tags: ["Auth"],
        summary: "Login user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", example: "raul@mail.com"),
                    new OA\Property(property: "password", type: "string", example: "password123"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Login berhasil"),
            new OA\Response(response: 401, description: "Unauthorized"),
        ]
    )
)]

#[OA\PathItem(
    path: "/api/register",
    post: new OA\Post(
        operationId: "auth_register",
        tags: ["Auth"],
        summary: "Register user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Raul Mahya"),
                    new OA\Property(property: "email", type: "string", example: "raul@mail.com"),
                    new OA\Property(property: "password", type: "string", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", example: "password123"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Register berhasil"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )
)]

#[OA\PathItem(
    path: "/api/logout",
    post: new OA\Post(
        operationId: "auth_logout",
        tags: ["Auth"],
        summary: "Logout user",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Logout berhasil"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )
)]

#[OA\PathItem(
    path: "/api/auth/google",
    get: new OA\Get(
        operationId: "auth_google_redirect",
        tags: ["Auth"],
        summary: "Login using Google (SSO)",
        description: "Redirect user to Google OAuth login page",
        responses: [
            new OA\Response(response: 302, description: "Redirect to Google"),
        ]
    )
)]

#[OA\PathItem(
    path: "/api/auth/google/callback",
    get: new OA\Get(
        operationId: "auth_google_callback",
        tags: ["Auth"],
        summary: "Google OAuth callback",
        description: "Handle Google OAuth callback and return API token",
        responses: [
            new OA\Response(
                response: 200,
                description: "Login success",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean"),
                        new OA\Property(property: "token", type: "string"),
                        new OA\Property(property: "user", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Google authentication failed"),
        ]
    )
)]


/* =====================
 | USER PROFILE
 ===================== */

#[OA\PathItem(
    path: "/api/users",
    get: new OA\Get(
        operationId: "users_get_all",
        tags: ["User Profile"],
        summary: "Get all users",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "OK"),
        ]
    ),
    post: new OA\Post(
        operationId: "users_create",
        tags: ["User Profile"],
        summary: "Create user",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "phone", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "User created"),
        ]
    )
)]

#[OA\PathItem(
    path: "/api/users/{id}",
    get: new OA\Get(
        operationId: "users_get_by_id",
        tags: ["User Profile"],
        summary: "Get user by ID",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    ),
    put: new OA\Put(
        operationId: "users_update",
        tags: ["User Profile"],
        summary: "Update user",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "phone", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Updated"),
        ]
    ),
    delete: new OA\Delete(
        operationId: "users_delete",
        tags: ["User Profile"],
        summary: "Delete user",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Deleted"),
        ]
    )
)]

/* =====================
 | SYSTEM
 ===================== */

#[OA\PathItem(
    path: "/api/_health",
    get: new OA\Get(
        operationId: "system_health",
        tags: ["System"],
        summary: "Health check",
        responses: [
            new OA\Response(response: 200, description: "OK"),
        ]
    )
)]

class SwaggerBootstrap {}
