<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

ini_set("display_errors", "1");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| MAIN LOGIN SYSTEM
|--------------------------------------------------------------------------
*/


$error = "";


/*
|--------------------------------------------------------------------------
| HELPER
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
| CHECK TABLE
|--------------------------------------------------------------------------
*/

try {

    $tableCheck = $conn->query("
        SELECT COUNT(*)

        FROM information_schema.tables

        WHERE table_schema = DATABASE()

        AND table_name = 'users'
    ");


    if (
        (int)$tableCheck->fetchColumn() === 0
    ) {

        throw new Exception(
            "The users table does not exist."
        );
    }


} catch (
    Throwable $e
) {

    $error =
        "Login system error: "
        .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    $error === ""
) {

    $username =
        trim(
            (string)
            (
                $_POST["username"]
                ?? ""
            )
        );


    $password =
        (string)
        (
            $_POST["password"]
            ?? ""
        );


    if (
        $username === ""
        ||
        $password === ""
    ) {

        $error =
            "Please enter your username and password.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | GET ACTUAL USERS COLUMNS
            |--------------------------------------------------------------------------
            |
            | We deliberately use SELECT *
            | so this login system does not assume
            | first_name, last_name, email, etc.
            |
            */

            $stmt =
                $conn->prepare("
                    SELECT *

                    FROM users

                    WHERE username = ?

                    LIMIT 1
                ");


            $stmt->execute([
                $username
            ]);


            $user =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
            |--------------------------------------------------------------------------
            | USER NOT FOUND
            |--------------------------------------------------------------------------
            */

            if (
                !$user
            ) {

                $error =
                    "Invalid username or password.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | PASSWORD COLUMN
                |--------------------------------------------------------------------------
                */

                $storedPassword =
                    $user["password"]
                    ??
                    $user["password_hash"]
                    ??
                    null;


                if (
                    !$storedPassword
                ) {

                    throw new Exception(
                        "The users table does not contain a valid password field."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | VERIFY PASSWORD
                |--------------------------------------------------------------------------
                */

                if (
                    !password_verify(
                        $password,
                        (string)
                        $storedPassword
                    )
                ) {

                    $error =
                        "Invalid username or password.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK ACCOUNT STATUS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        array_key_exists(
                            "status",
                            $user
                        )
                    ) {

                        $status =
                            strtolower(
                                trim(
                                    (string)
                                    $user["status"]
                                )
                            );


                        if (
                            in_array(
                                $status,
                                [
                                    "inactive",
                                    "disabled",
                                    "blocked",
                                    "suspended"
                                ],
                                true
                            )
                        ) {

                            throw new Exception(
                                "This account is currently inactive. Please contact the school administrator."
                            );
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | ROLE
                    |--------------------------------------------------------------------------
                    */

                    $role =
                        strtolower(
                            trim(
                                (string)
                                (
                                    $user["role"]
                                    ??
                                    ""
                                )
                            )
                        );


                    if (
                        $role === ""
                    ) {

                        throw new Exception(
                            "This account does not have a valid user role."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | REGENERATE SESSION ID
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(
                        true
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | STORE SESSION
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION[
                        "user_id"
                    ] =
                        (int)
                        $user["id"];


                    $_SESSION[
                        "username"
                    ] =
                        (string)
                        $user["username"];


                    $_SESSION[
                        "role"
                    ] =
                        $role;


                    /*
                    |--------------------------------------------------------------------------
                    | OPTIONAL USER INFORMATION
                    |--------------------------------------------------------------------------
                    |
                    | We only store these if they actually exist.
                    |
                    */

                    $_SESSION[
                        "first_name"
                    ] =
                        (string)
                        (
                            $user["first_name"]
                            ??
                            ""
                        );


                    $_SESSION[
                        "last_name"
                    ] =
                        (string)
                        (
                            $user["last_name"]
                            ??
                            ""
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECT BY ROLE
                    |--------------------------------------------------------------------------
                    */

                    switch (
                        $role
                    ) {

                        case "admin":

                            header(
                                "Location: admin/dashboard.php"
                            );

                            exit;


                        case "teacher":

                            header(
                                "Location: teacher/dashboard.php"
                            );

                            exit;


                        case "student":

                            header(
                                "Location: student/dashboard.php"
                            );

                            exit;


                        default:

                            /*
                            |--------------------------------------------------------------------------
                            | UNKNOWN ROLE
                            |--------------------------------------------------------------------------
                            */

                            $error =
                                "Your account role is not recognised by the HIBS system.";

                            break;
                    }
                }
            }


        } catch (
            Throwable $e
        ) {

            $error =
                "Login system error: "
                .
                $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
HIBS Reports | Login
</title>


<style>

* {
    box-sizing: border-box;
}


:root {

    --navy: #263238;

    --navy-light: #37474f;

    --slate: #607d8b;

    --background: #f1f3f2;

    --white: #ffffff;

    --border: #d5d9d7;

    --text: #25343b;

    --muted: #718087;

    --error-bg: #fff0f0;

    --error-border: #e7c5c5;

    --error-text: #8b4b4b;

}


/*
|--------------------------------------------------------------------------
| BODY
|--------------------------------------------------------------------------
*/

body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 30px 15px;

    background:
        var(--background);

    color:
        var(--text);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

}


/*
|--------------------------------------------------------------------------
| LOGIN CARD
|--------------------------------------------------------------------------
*/

.login-card {

    width: 100%;

    max-width: 420px;

    background:
        var(--white);

    border:
        1px solid
        var(--border);

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.login-header {

    padding:
        32px 30px 27px;

    text-align: center;

    border-bottom:
        1px solid
        #e3e6e4;

}


.school-name {

    margin: 0;

    color:
        var(--navy);

    font-size: 23px;

    font-weight: 700;

    letter-spacing:
        1.2px;

}


.school-subtitle {

    margin-top: 8px;

    color:
        var(--slate);

    font-size: 9px;

    line-height: 1.6;

    letter-spacing:
        .2px;

}


/*
|--------------------------------------------------------------------------
| FORM AREA
|--------------------------------------------------------------------------
*/

.login-body {

    padding:
        32px 30px;

}


.login-title {

    margin: 0 0 24px;

    font-size: 19px;

    font-weight: 600;

}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.error {

    margin-bottom: 20px;

    padding:
        13px 14px;

    color:
        var(--error-text);

    background:
        var(--error-bg);

    border:
        1px solid
        var(--error-border);

    font-size: 11px;

    line-height: 1.6;

}


/*
|--------------------------------------------------------------------------
| FORM GROUP
|--------------------------------------------------------------------------
*/

.form-group {

    margin-bottom: 19px;

}


label {

    display: block;

    margin-bottom: 7px;

    color:
        #58686f;

    font-size: 10px;

    font-weight: 600;

}


input {

    width: 100%;

    height: 45px;

    padding:
        0 12px;

    border:
        1px solid
        #c8cfcc;

    border-radius: 4px;

    background:
        white;

    color:
        var(--text);

    font-family:
        inherit;

    font-size: 13px;

}


input:focus {

    outline: none;

    border-color:
        var(--slate);

    box-shadow:
        0 0 0 2px
        rgba(
            96,
            125,
            139,
            .08
        );

}


/*
|--------------------------------------------------------------------------
| LOGIN BUTTON
|--------------------------------------------------------------------------
*/

.login-button {

    width: 100%;

    height: 45px;

    margin-top: 3px;

    border: 0;

    border-radius: 4px;

    background:
        var(--navy);

    color:
        white;

    font-family:
        inherit;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;

}


.login-button:hover {

    background:
        var(--navy-light);

}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.login-footer {

    padding:
        17px 20px;

    border-top:
        1px solid
        #e3e6e4;

    text-align: center;

    color:
        var(--slate);

    font-size: 9px;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media(max-width:500px) {

    body {

        padding:
            15px;

    }


    .login-header {

        padding:
            27px 20px 23px;

    }


    .login-body {

        padding:
            27px 20px;

    }


    .school-name {

        font-size: 21px;

    }

}

</style>

</head>


<body>


<div class="login-card">


<!-- HEADER -->

<div class="login-header">

    <h1 class="school-name">

        HIBS REPORTS

    </h1>


    <div class="school-subtitle">

        HILLTOP INTERNATIONAL<br>
        BRITISH SCHOOL

    </div>

</div>


<!-- BODY -->

<div class="login-body">


<h2 class="login-title">

    Sign in to your account

</h2>


<?php if (
    $error !== ""
): ?>

<div class="error">

    <?= h(
        $error
    ) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    autocomplete="off"
>


<div class="form-group">

<label for="username">

    Username

</label>


<input
    type="text"
    id="username"
    name="username"
    autocomplete="username"
    required
    autofocus
>


</div>


<div class="form-group">

<label for="password">

    Password

</label>


<input
    type="password"
    id="password"
    name="password"
    autocomplete="current-password"
    required
>


</div>


<button
    type="submit"
    class="login-button"
>

    Sign In

</button>


</form>


</div>


<!-- FOOTER -->

<div class="login-footer">

    HIBS Academic Reporting System

</div>


</div>


</body>

</html>
