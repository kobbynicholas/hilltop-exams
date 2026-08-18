<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

ini_set("display_errors", "1");
error_reporting(E_ALL);

$error = "";


/*
|--------------------------------------------------------------------------
| ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $_SESSION["user_id"]
    )
) {

    $role =
        strtolower(
            (string)
            (
                $_SESSION["role"]
                ?? ""
            )
        );


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
}


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
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

            $stmt =
                $conn->prepare("
                    SELECT

                        id,
                        username,
                        password,
                        role,
                        status,
                        first_name,
                        last_name

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


            if (
                !$user
                ||
                !password_verify(
                    $password,
                    $user["password"]
                )
            ) {

                $error =
                    "Invalid username or password.";

            } elseif (
                strtolower(
                    (string)
                    $user["status"]
                )
                !== "active"
            ) {

                $error =
                    "This account has been deactivated.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | SECURE SESSION
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(
                    true
                );


                $_SESSION[
                    "user_id"
                ] =
                    (int)
                    $user["id"];


                $_SESSION[
                    "username"
                ] =
                    $user["username"];


                $_SESSION[
                    "role"
                ] =
                    strtolower(
                        (string)
                        $user["role"]
                    );


                $_SESSION[
                    "first_name"
                ] =
                    $user["first_name"]
                    ?? "";


                $_SESSION[
                    "last_name"
                ] =
                    $user["last_name"]
                    ?? "";


                /*
                |--------------------------------------------------------------------------
                | REDIRECT
                |--------------------------------------------------------------------------
                */

                switch (
                    $_SESSION["role"]
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

                        session_destroy();

                        $error =
                            "This account has an invalid role.";
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

body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        #eef0ee;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    color:
        #263238;

}

.login {

    width:
        420px;

    max-width:
        calc(100% - 30px);

    background:
        white;

    border:
        1px solid
        #dfe2df;

    box-shadow:
        0 12px 40px
        rgba(0,0,0,.06);

}

.header {

    padding:
        30px;

    text-align:
        center;

    border-bottom:
        1px solid
        #e6e8e6;

}

.logo {

    font-size:
        22px;

    font-weight:
        700;

    letter-spacing:
        1.5px;

}

.school {

    margin-top:
        7px;

    color:
        #7a878c;

    font-size:
        10px;

    line-height:
        1.5;

}

.body {

    padding:
        30px;

}

.title {

    margin-bottom:
        20px;

    font-size:
        17px;

    font-weight:
        600;

}

label {

    display:
        block;

    margin-bottom:
        7px;

    color:
        #657277;

    font-size:
        10px;

    font-weight:
        600;

}

input {

    width:
        100%;

    height:
        44px;

    padding:
        0 12px;

    margin-bottom:
        17px;

    border:
        1px solid
        #ccd1cf;

    border-radius:
        4px;

    font-size:
        13px;

    outline:
        none;

}

input:focus {

    border-color:
        #607d8b;

}

button {

    width:
        100%;

    height:
        45px;

    border:
        0;

    background:
        #263238;

    color:
        white;

    border-radius:
        4px;

    font-size:
        12px;

    font-weight:
        600;

    cursor:
        pointer;

}

button:hover {

    background:
        #37474f;

}

.error {

    margin-bottom:
        18px;

    padding:
        12px;

    background:
        #faeeee;

    border:
        1px solid
        #e4cccc;

    color:
        #914f4f;

    font-size:
        10px;

}

.footer {

    padding:
        16px;

    border-top:
        1px solid
        #e6e8e6;

    text-align:
        center;

    color:
        #899397;

    font-size:
        9px;

}

</style>

</head>


<body>


<div class="login">


<div class="header">

<div class="logo">

HIBS REPORTS

</div>


<div class="school">

HILLTOP INTERNATIONAL<br>

BRITISH SCHOOL

</div>

</div>


<div class="body">


<div class="title">

Sign in to your account

</div>


<?php if (
    $error !== ""
): ?>

<div class="error">

<?= htmlspecialchars(
    $error
) ?>

</div>

<?php endif; ?>


<form
    method="POST"
>


<label>
    Username
</label>


<input
    type="text"
    name="username"
    autocomplete="username"
    required
    autofocus
>


<label>
    Password
</label>


<input
    type="password"
    name="password"
    autocomplete="current-password"
    required
>


<button
    type="submit"
>
    Sign In
</button>


</form>


</div>


<div class="footer">

HIBS Academic Reporting System

</div>


</div>


</body>

</html>
