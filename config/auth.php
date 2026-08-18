<?php

/*
|--------------------------------------------------------------------------
| HIBS REPORTS AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (
        empty($_SESSION["user_id"])
    ) {
        header(
            "Location: ../login.php"
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE ADMIN
|--------------------------------------------------------------------------
*/

function require_admin(): void
{
    require_login();

    if (
        ($_SESSION["role"] ?? "") !== "admin"
    ) {
        http_response_code(403);

        exit(
            "Access denied."
        );
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE TEACHER
|--------------------------------------------------------------------------
*/

function require_teacher(): void
{
    require_login();

    if (
        ($_SESSION["role"] ?? "") !== "teacher"
    ) {
        http_response_code(403);

        exit(
            "Access denied."
        );
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE STUDENT
|--------------------------------------------------------------------------
*/

function require_student(): void
{
    require_login();

    if (
        ($_SESSION["role"] ?? "") !== "student"
    ) {
        http_response_code(403);

        exit(
            "Access denied."
        );
    }
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (
        empty(
            $_SESSION["csrf_token"]
        )
    ) {

        $_SESSION["csrf_token"] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION[
        "csrf_token"
    ];
}


/*
|--------------------------------------------------------------------------
| VERIFY CSRF
|--------------------------------------------------------------------------
*/

function verify_csrf(): void
{
    $token =
        $_POST[
            "csrf_token"
        ] ?? "";

    if (
        empty($token) ||
        empty(
            $_SESSION[
                "csrf_token"
            ]
        ) ||
        !hash_equals(
            $_SESSION[
                "csrf_token"
            ],
            $token
        )
    ) {

        http_response_code(419);

        exit(
            "Invalid security token. Please go back and try again."
        );
    }
}
