<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

ini_set("display_errors", "1");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| LOGIN SYSTEM
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
| FIND USER ROLE
|--------------------------------------------------------------------------
|
| The system first uses users.role.
|
| If role is missing, it checks:
|
| students.user_id
| teachers.user_id
|
|--------------------------------------------------------------------------
*/

function determineUserRole(
    PDO $conn,
    int $userId,
    string $existingRole
): string {

    $existingRole =
        strtolower(
            trim(
                $existingRole
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Existing valid role
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $existingRole,
            [
                "admin",
                "teacher",
                "student"
            ],
            true
        )
    ) {

        return $existingRole;
    }


    /*
    |--------------------------------------------------------------------------
    | Check STUDENTS
    |--------------------------------------------------------------------------
    */

    try {

        $stmt =
            $conn->prepare("
                SELECT id

                FROM students

                WHERE user_id = ?

                LIMIT 1
            ");


        $stmt->execute([
            $userId
        ]);


        if (
            $stmt->fetchColumn()
        ) {

            return "student";
        }

    } catch (
        Throwable $e
    ) {

        // Ignore and continue.
    }


    /*
    |--------------------------------------------------------------------------
    | Check TEACHERS
    |--------------------------------------------------------------------------
    */

    try {

        $stmt =
            $conn->prepare("
                SELECT id

                FROM teachers

                WHERE user_id = ?

                LIMIT 1
            ");


        $stmt->execute([
            $userId
        ]);


        if (
            $stmt->fetchColumn()
        ) {

            return "teacher";
        }

    } catch (
        Throwable $e
    ) {

        // Ignore and continue.
    }


    /*
    |--------------------------------------------------------------------------
    | No role found
    |--------------------------------------------------------------------------
    */

    return "";
}


/*
|--------------------------------------------------------------------------
| UPDATE ROLE
|--------------------------------------------------------------------------
*/

function updateUserRole(
    PDO $conn,
    int $userId,
    string $role
): void {

    try {

        $stmt =
            $conn->prepare("
                UPDATE users

                SET role = ?

                WHERE id = ?
            ");


        $stmt->execute([
            $role,
            $userId
        ]);

    } catch (
        Throwable $e
    ) {

        // Do not stop login if role update fails.
    }
}


/*
|--------------------------------------------------------------------------
| CHECK USERS TABLE
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $conn->query("
            SELECT COUNT(*)

            FROM information_schema.tables

            WHERE table_schema = DATABASE()

            AND table_name = 'users'
        ");


    if (
        (int)$stmt->fetchColumn() === 0
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
| PROCESS LOGIN
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
                $_POST[
                    "username"
                ]
                ?? ""
            )
        );


    $password =
        (string)
        (
            $_POST[
                "password"
            ]
            ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

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
            | GET USER
            |--------------------------------------------------------------------------
            |
            | SELECT * deliberately.
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
                | PASSWORD
                |--------------------------------------------------------------------------
                */

                $storedPassword =
                    $user["password"]
                    ??
                    $user["password_hash"]
                    ??
                    "";


                if (
                    $storedPassword === ""
                ) {

                    throw new Exception(
                        "This account does not contain a valid password."
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
                    | STATUS
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
                                    (
                                        $user[
                                            "status"
                                        ]
                                        ??
                                        ""
                                    )
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
                    | DETERMINE ROLE
                    |--------------------------------------------------------------------------
                    */

                    $userId =
                        (int)
                        (
                            $user[
                                "id"
                            ]
                            ?? 0
                        );


                    $existingRole =
                        (string)
                        (
                            $user[
                                "role"
                            ]
                            ??
                            ""
                        );


                    $role =
                        determineUserRole(
                            $conn,
                            $userId,
                            $existingRole
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | ROLE NOT FOUND
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $role === ""
                    ) {

                        throw new Exception(
                            "This account is not linked to a student, teacher, or administrator role."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | AUTOMATICALLY REPAIR ROLE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        strtolower(
                            trim(
                                $existingRole
                            )
                        )
                        !==
                        $role
                    ) {

                        updateUserRole(
                            $conn,
                            $userId,
                            $role
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SESSION SECURITY
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(
                        true
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | SESSION DATA
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION[
                        "user_id"
                    ] =
                        $userId;


                    $_SESSION[
                        "username"
                    ] =
                        (string)
                        (
                            $user[
                                "username"
                            ]
                            ?? ""
                        );


                    $_SESSION[
                        "role"
                    ] =
                        $role;


                    /*
                    |--------------------------------------------------------------------------
                    | OPTIONAL USER DATA
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION[
                        "first_name"
                    ] =
                        (string)
                        (
                            $user[
                                "first_name"
                            ]
                            ??
                            ""
                        );


                    $_SESSION[
                        "last_name"
                    ] =
                        (string)
                        (
                            $user[
                                "last_name"
                            ]
                            ??
                            ""
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECT
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $role === "admin"
                    ) {

                        header(
                            "Location: admin/dashboard.php"
                        );

                        exit;
                    }


                    if (
                        $role === "teacher"
                    ) {

                        header(
                            "Location: teacher/dashboard.php"
                        );

                        exit;
                    }


                    if (
                        $role === "student"
                    ) {

                        header(
                            "Location: student/dashboard.php"
                        );

                        exit;
                    }


                    throw new Exception(
                        "The account role could not be processed."
                    );
                }
            }


        } catch (
            Throwable $e
        ) {

            $error =
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


.login-card {

    width: 100%;

    max-width: 420px;

    background:
        var(--white);

    border:
        1px solid
        var(--border);

}


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

}


.login-body {

    padding:
        32px 30px;

}


.login-title {

    margin:
        0 0 24px;

    font-size: 19px;

    font-weight: 600;

}


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

}


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


<div class="login-header">

    <h1 class="school-name">

        HIBS REPORTS

    </h1>


    <div class="school-subtitle">

        HILLTOP INTERNATIONAL<br>

        BRITISH SCHOOL

    </div>

</div>


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


<div class="login-footer">

    HIBS Academic Reporting System

</div>


</div>


</body>

</html>
