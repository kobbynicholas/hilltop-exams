<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

ini_set("display_errors", "1");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| ACCOUNT MANAGEMENT
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["user_id"]) ||
    strtolower((string)($_SESSION["role"] ?? "")) !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
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


function tableExists(
    PDO $conn,
    string $table
): bool {

    try {

        $stmt = $conn->prepare("
            SELECT COUNT(*)

            FROM information_schema.tables

            WHERE table_schema = DATABASE()

            AND table_name = ?
        ");

        $stmt->execute([
            $table
        ]);

        return (int)$stmt->fetchColumn() > 0;

    } catch (Throwable $e) {

        return false;
    }
}


function getColumns(
    PDO $conn,
    string $table
): array {

    try {

        $stmt = $conn->prepare("
            SELECT column_name

            FROM information_schema.columns

            WHERE table_schema = DATABASE()

            AND table_name = ?

            ORDER BY ordinal_position
        ");

        $stmt->execute([
            $table
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );

    } catch (Throwable $e) {

        return [];
    }
}


function hasColumn(
    array $columns,
    string $column
): bool {

    return in_array(
        $column,
        $columns,
        true
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRED TABLES
|--------------------------------------------------------------------------
*/

if (
    !tableExists(
        $conn,
        "users"
    )
) {

    die("
        <div style='
            font-family:Arial;
            padding:35px;
            background:#fff4f4;
            color:#8a4b4b;
        '>

        <h2>HIBS Reports Account System</h2>

        <p>
            The <strong>users</strong> table does not exist.
        </p>

        <p>
            Run
            <strong>setup_accounts.php</strong>
            first.
        </p>

        </div>
    ");
}


if (
    !tableExists(
        $conn,
        "students"
    )
    ||
    !tableExists(
        $conn,
        "teachers"
    )
) {

    die("
        <div style='
            font-family:Arial;
            padding:35px;
            background:#fff4f4;
            color:#8a4b4b;
        '>

        <h2>HIBS Reports Database Error</h2>

        <p>
            The students or teachers table does not exist.
        </p>

        </div>
    ");
}


/*
|--------------------------------------------------------------------------
| GET ACTUAL COLUMNS
|--------------------------------------------------------------------------
*/

$userColumns =
    getColumns(
        $conn,
        "users"
    );

$studentColumns =
    getColumns(
        $conn,
        "students"
    );

$teacherColumns =
    getColumns(
        $conn,
        "teachers"
    );

$classColumns =
    getColumns(
        $conn,
        "classes"
    );


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$success = "";
$error = "";

$createdUsername = "";
$createdPassword = "";
$createdPerson = "";


/*
|--------------------------------------------------------------------------
| PERSON NAME HELPER
|--------------------------------------------------------------------------
*/

function getPersonName(
    array $person,
    array $columns
): string {

    /*
    |--------------------------------------------------------------------------
    | Full name fields
    |--------------------------------------------------------------------------
    */

    foreach (
        [
            "full_name",
            "name",
            "student_name",
            "teacher_name"
        ] as $field
    ) {

        if (
            hasColumn(
                $columns,
                $field
            )
            &&
            trim(
                (string)
                (
                    $person[$field]
                    ?? ""
                )
            ) !== ""
        ) {

            return trim(
                (string)
                $person[$field]
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | First / middle / last
    |--------------------------------------------------------------------------
    */

    $parts = [];


    foreach (
        [
            "first_name",
            "middle_name",
            "last_name"
        ] as $field
    ) {

        if (
            hasColumn(
                $columns,
                $field
            )
            &&
            trim(
                (string)
                (
                    $person[$field]
                    ?? ""
                )
            ) !== ""
        ) {

            $parts[] =
                trim(
                    (string)
                    $person[$field]
                );
        }
    }


    if (
        count($parts) > 0
    ) {

        return implode(
            " ",
            $parts
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    */

    return "Person #" .
        (
            $person["id"]
            ?? ""
        );
}


/*
|--------------------------------------------------------------------------
| CREATE ACCOUNT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";


    try {

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

        if (
            $action ===
            "create_account"
        ) {

            $personType =
                strtolower(
                    trim(
                        (string)
                        (
                            $_POST[
                                "person_type"
                            ]
                            ?? ""
                        )
                    )
                );


            $personId =
                filter_input(
                    INPUT_POST,
                    "person_id",
                    FILTER_VALIDATE_INT
                );


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
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if (
                !in_array(
                    $personType,
                    [
                        "student",
                        "teacher"
                    ],
                    true
                )
            ) {

                throw new Exception(
                    "Please select Student or Teacher."
                );
            }


            if (
                !$personId
            ) {

                throw new Exception(
                    "Please select a person."
                );
            }


            if (
                $username === ""
            ) {

                throw new Exception(
                    "Please enter a username."
                );
            }


            if (
                strlen($username) < 4
            ) {

                throw new Exception(
                    "Username must contain at least 4 characters."
                );
            }


            if (
                !preg_match(
                    '/^[A-Za-z0-9._-]+$/',
                    $username
                )
            ) {

                throw new Exception(
                    "Username may contain only letters, numbers, dots, underscores and hyphens."
                );
            }


            if (
                strlen($password) < 8
            ) {

                throw new Exception(
                    "Password must contain at least 8 characters."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | SELECT PERSON
            |--------------------------------------------------------------------------
            */

            $table =
                $personType === "student"
                ? "students"
                : "teachers";


            $columns =
                $personType === "student"
                ? $studentColumns
                : $teacherColumns;


            $stmt =
                $conn->prepare("
                    SELECT *

                    FROM `$table`

                    WHERE id = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $personId
            ]);


            $person =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$person
            ) {

                throw new Exception(
                    ucfirst(
                        $personType
                    )
                    .
                    " not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK EXISTING USER_ID
            |--------------------------------------------------------------------------
            */

            if (
                hasColumn(
                    $columns,
                    "user_id"
                )
                &&
                !empty(
                    $person["user_id"]
                )
            ) {

                throw new Exception(
                    "This "
                    .
                    $personType
                    .
                    " already has a login account."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK USERNAME
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT id

                    FROM users

                    WHERE username = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $username
            ]);


            if (
                $stmt->fetchColumn()
            ) {

                throw new Exception(
                    "That username is already in use."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET PERSON NAME
            |--------------------------------------------------------------------------
            */

            $personName =
                getPersonName(
                    $person,
                    $columns
                );


            /*
            |--------------------------------------------------------------------------
            | INSERT USER
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | We only insert columns that actually exist.
            |
            */

            $insertColumns = [
                "username",
                "password",
                "role"
            ];


            $insertValues = [
                $username,
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                $personType
            ];


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            if (
                hasColumn(
                    $userColumns,
                    "status"
                )
            ) {

                $insertColumns[] =
                    "status";

                $insertValues[] =
                    "active";
            }


            /*
            |--------------------------------------------------------------------------
            | FIRST NAME
            |--------------------------------------------------------------------------
            */

            if (
                hasColumn(
                    $userColumns,
                    "first_name"
                )
            ) {

                $firstName =
                    "";


                if (
                    hasColumn(
                        $columns,
                        "first_name"
                    )
                ) {

                    $firstName =
                        trim(
                            (string)
                            (
                                $person[
                                    "first_name"
                                ]
                                ?? ""
                            )
                        );
                }


                $insertColumns[] =
                    "first_name";

                $insertValues[] =
                    $firstName;
            }


            /*
            |--------------------------------------------------------------------------
            | LAST NAME
            |--------------------------------------------------------------------------
            */

            if (
                hasColumn(
                    $userColumns,
                    "last_name"
                )
            ) {

                $lastName =
                    "";


                if (
                    hasColumn(
                        $columns,
                        "last_name"
                    )
                ) {

                    $lastName =
                        trim(
                            (string)
                            (
                                $person[
                                    "last_name"
                                ]
                                ?? ""
                            )
                        );
                }


                $insertColumns[] =
                    "last_name";

                $insertValues[] =
                    $lastName;
            }


            /*
            |--------------------------------------------------------------------------
            | EMAIL
            |--------------------------------------------------------------------------
            */

            if (
                hasColumn(
                    $userColumns,
                    "email"
                )
                &&
                hasColumn(
                    $columns,
                    "email"
                )
            ) {

                $email =
                    trim(
                        (string)
                        (
                            $person[
                                "email"
                            ]
                            ?? ""
                        )
                    );


                if (
                    $email !== ""
                ) {

                    $insertColumns[] =
                        "email";

                    $insertValues[] =
                        $email;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD INSERT
            |--------------------------------------------------------------------------
            */

            $placeholders =
                implode(
                    ", ",
                    array_fill(
                        0,
                        count(
                            $insertColumns
                        ),
                        "?"
                    )
                );


            $columnList =
                implode(
                    ", ",
                    array_map(
                        function (
                            $column
                        ) {

                            return "`"
                                .
                                $column
                                .
                                "`";

                        },
                        $insertColumns
                    )
                );


            $sql = "
                INSERT INTO users
                (
                    $columnList
                )

                VALUES
                (
                    $placeholders
                )
            ";


            $stmt =
                $conn->prepare(
                    $sql
                );


            $stmt->execute(
                $insertValues
            );


            $userId =
                (int)
                $conn->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | LINK PERSON TO USER
            |--------------------------------------------------------------------------
            */

            if (
                hasColumn(
                    $columns,
                    "user_id"
                )
            ) {

                $stmt =
                    $conn->prepare("
                        UPDATE `$table`

                        SET user_id = ?

                        WHERE id = ?
                    ");

                $stmt->execute([
                    $userId,
                    $personId
                ]);

            } else {

                throw new Exception(
                    "The "
                    .
                    $table
                    .
                    " table does not contain a user_id column."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $createdUsername =
                $username;

            $createdPassword =
                $password;

            $createdPerson =
                $personName;


            $success =
                ucfirst(
                    $personType
                )
                .
                " login account created successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | CHANGE STATUS
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "change_status"
        ) {

            $userId =
                filter_input(
                    INPUT_POST,
                    "user_id",
                    FILTER_VALIDATE_INT
                );


            $newStatus =
                strtolower(
                    trim(
                        (string)
                        (
                            $_POST[
                                "new_status"
                            ]
                            ?? ""
                        )
                    )
                );


            if (
                !$userId
            ) {

                throw new Exception(
                    "Invalid account."
                );
            }


            if (
                !in_array(
                    $newStatus,
                    [
                        "active",
                        "inactive"
                    ],
                    true
                )
            ) {

                throw new Exception(
                    "Invalid status."
                );
            }


            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        role

                    FROM users

                    WHERE id = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $userId
            ]);


            $account =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$account
            ) {

                throw new Exception(
                    "Account not found."
                );
            }


            if (
                $account["role"] ===
                "admin"
            ) {

                throw new Exception(
                    "Administrator accounts cannot be changed here."
                );
            }


            if (
                !hasColumn(
                    $userColumns,
                    "status"
                )
            ) {

                throw new Exception(
                    "The users table does not contain a status column."
                );
            }


            $stmt =
                $conn->prepare("
                    UPDATE users

                    SET status = ?

                    WHERE id = ?
                ");

            $stmt->execute([
                $newStatus,
                $userId
            ]);


            $success =
                "Account status changed to "
                .
                ucfirst(
                    $newStatus
                )
                .
                ".";
        }


        /*
        |--------------------------------------------------------------------------
        | RESET PASSWORD
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "reset_password"
        ) {

            $userId =
                filter_input(
                    INPUT_POST,
                    "user_id",
                    FILTER_VALIDATE_INT
                );


            $newPassword =
                (string)
                (
                    $_POST[
                        "new_password"
                    ]
                    ?? ""
                );


            if (
                !$userId
            ) {

                throw new Exception(
                    "Invalid account."
                );
            }


            if (
                strlen(
                    $newPassword
                ) < 8
            ) {

                throw new Exception(
                    "New password must contain at least 8 characters."
                );
            }


            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        role

                    FROM users

                    WHERE id = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $userId
            ]);


            $account =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$account
            ) {

                throw new Exception(
                    "Account not found."
                );
            }


            if (
                $account["role"] ===
                "admin"
            ) {

                throw new Exception(
                    "Administrator password cannot be reset here."
                );
            }


            $passwordHash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            $stmt =
                $conn->prepare("
                    UPDATE users

                    SET password = ?

                    WHERE id = ?
                ");

            $stmt->execute([
                $passwordHash,
                $userId
            ]);


            $success =
                "Password reset successfully.";
        }


        else {

            throw new Exception(
                "Invalid account action."
            );
        }


    } catch (
        Throwable $e
    ) {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| LOAD STUDENTS
|--------------------------------------------------------------------------
*/

$students = [];

try {

    $studentSelect = "s.*";


    if (
        tableExists(
            $conn,
            "classes"
        )
        &&
        hasColumn(
            $studentColumns,
            "class_id"
        )
        &&
        hasColumn(
            $classColumns,
            "class_name"
        )
    ) {

        $studentSelect .= ",
            c.class_name
        ";


        $studentSql = "
            SELECT
                $studentSelect

            FROM students s

            LEFT JOIN classes c
                ON c.id = s.class_id

            ORDER BY
                s.id DESC
        ";

    } else {

        $studentSql = "
            SELECT *

            FROM students

            ORDER BY id DESC
        ";
    }


    $stmt =
        $conn->query(
            $studentSql
        );


    $students =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $e
) {

    $error =
        "Unable to load students: "
        .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| LOAD TEACHERS
|--------------------------------------------------------------------------
*/

$teachers = [];

try {

    $stmt =
        $conn->query("
            SELECT *

            FROM teachers

            ORDER BY id DESC
        ");


    $teachers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $e
) {

    $error =
        "Unable to load teachers: "
        .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| LOAD USERS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We use SELECT * here instead of assuming
| first_name / last_name exist.
|
*/

$accounts = [];

try {

    $stmt =
        $conn->query("
            SELECT *

            FROM users

            WHERE role IN (
                'student',
                'teacher'
            )

            ORDER BY id DESC
        ");


    $accounts =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $e
) {

    $error =
        "Unable to load accounts: "
        .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| BUILD PERSON LOOKUPS
|--------------------------------------------------------------------------
*/

$studentLookup = [];

foreach (
    $students as $student
) {

    $studentLookup[
        (int)$student["id"]
    ] =
        $student;
}


$teacherLookup = [];

foreach (
    $teachers as $teacher
) {

    $teacherLookup[
        (int)$teacher["id"]
    ] =
        $teacher;
}


/*
|--------------------------------------------------------------------------
| DETERMINE ACCOUNT PERSON
|--------------------------------------------------------------------------
*/

foreach (
    $accounts as &$account
) {

    $account["person_name"] =
        "";


    $account["person_identifier"] =
        "";


    $userId =
        (int)
        (
            $account["id"]
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | Find linked person through user_id
    |--------------------------------------------------------------------------
    */

    if (
        $account["role"] ===
        "student"
    ) {

        foreach (
            $students as $student
        ) {

            if (
                hasColumn(
                    $studentColumns,
                    "user_id"
                )
                &&
                (int)
                (
                    $student["user_id"]
                    ?? 0
                )
                ===
                $userId
            ) {

                $account[
                    "person_name"
                ] =
                    getPersonName(
                        $student,
                        $studentColumns
                    );


                if (
                    hasColumn(
                        $studentColumns,
                        "student_id"
                    )
                ) {

                    $account[
                        "person_identifier"
                    ] =
                        (string)
                        (
                            $student[
                                "student_id"
                            ]
                            ?? ""
                        );
                }


                if (
                    isset(
                        $student[
                            "class_name"
                        ]
                    )
                ) {

                    $account[
                        "class_name"
                    ] =
                        $student[
                            "class_name"
                        ];
                }


                break;
            }
        }

    } else {

        foreach (
            $teachers as $teacher
        ) {

            if (
                hasColumn(
                    $teacherColumns,
                    "user_id"
                )
                &&
                (int)
                (
                    $teacher["user_id"]
                    ?? 0
                )
                ===
                $userId
            ) {

                $account[
                    "person_name"
                ] =
                    getPersonName(
                        $teacher,
                        $teacherColumns
                    );


                foreach (
                    [
                        "employee_id",
                        "staff_id",
                        "teacher_id"
                    ] as $field
                ) {

                    if (
                        hasColumn(
                            $teacherColumns,
                            $field
                        )
                        &&
                        !empty(
                            $teacher[$field]
                        )
                    ) {

                        $account[
                            "person_identifier"
                        ] =
                            (string)
                            $teacher[$field];

                        break;
                    }
                }


                break;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FALLBACK TO USERS TABLE
    |--------------------------------------------------------------------------
    */

    if (
        trim(
            $account[
                "person_name"
            ]
        ) === ""
    ) {

        $nameParts = [];


        foreach (
            [
                "first_name",
                "middle_name",
                "last_name"
            ] as $field
        ) {

            if (
                hasColumn(
                    $userColumns,
                    $field
                )
                &&
                !empty(
                    $account[$field]
                )
            ) {

                $nameParts[] =
                    trim(
                        (string)
                        $account[$field]
                    );
            }
        }


        if (
            count($nameParts)
        ) {

            $account[
                "person_name"
            ] =
                implode(
                    " ",
                    $nameParts
                );
        }
    }


    if (
        trim(
            $account[
                "person_name"
            ]
        ) === ""
    ) {

        $account[
            "person_name"
        ] =
            "Account #"
            .
            $userId;
    }

}

unset($account);


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$studentAccounts = 0;
$teacherAccounts = 0;
$activeAccounts = 0;
$inactiveAccounts = 0;


foreach (
    $accounts as $account
) {

    if (
        ($account["role"] ?? "")
        ===
        "student"
    ) {

        $studentAccounts++;

    } elseif (
        ($account["role"] ?? "")
        ===
        "teacher"
    ) {

        $teacherAccounts++;
    }


    if (
        ($account["status"] ?? "active")
        ===
        "active"
    ) {

        $activeAccounts++;

    } else {

        $inactiveAccounts++;
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
HIBS Reports | Account Management
</title>


<style>

* {
    box-sizing: border-box;
}


:root {

    --navy: #263238;
    --navy2: #37474f;
    --slate: #607d8b;

    --background: #f4f5f3;
    --white: #ffffff;

    --line: #dedfdd;

    --text: #27353b;
    --muted: #7c898e;

    --success: #477052;
    --success-bg: #edf5ef;

    --danger: #914f4f;
    --danger-bg: #faeeee;
}


body {

    margin: 0;

    background:
        var(--background);

    color:
        var(--text);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

}


/* SIDEBAR */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 245px;

    height: 100vh;

    padding: 25px 16px;

    background:
        var(--navy);

    color: white;

    overflow-y: auto;

}


.brand {

    padding:
        3px 11px 24px;

    margin-bottom:
        22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

}


.brand-title {

    font-size: 20px;

    font-weight: 700;

    letter-spacing: 1px;

}


.brand-subtitle {

    margin-top: 7px;

    color: #b4bec2;

    font-size: 9px;

    line-height: 1.7;

}


.nav-label {

    margin:
        0 10px 7px;

    color: #879398;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.nav a {

    display: flex;

    align-items: center;

    min-height: 43px;

    padding:
        0 11px;

    margin-bottom: 3px;

    color: #dce2e4;

    text-decoration: none;

    font-size: 12px;

    border-radius: 5px;

}


.nav a:hover {

    background:
        var(--navy2);

}


.nav a.active {

    background:
        #536a73;

}


.icon {

    width: 25px;

    font-size: 14px;

}


/* MAIN */

.main {

    margin-left: 245px;

    min-height: 100vh;

}


.topbar {

    height: 78px;

    display: flex;

    align-items: center;

    padding:
        0 32px;

    background:
        white;

    border-bottom:
        1px solid
        var(--line);

}


.topbar h1 {

    margin: 0;

    font-size: 22px;

    font-weight: 600;

}


.content {

    max-width: 1500px;

    padding:
        30px 32px 50px;

}


.heading {

    margin-bottom: 23px;

}


.heading h2 {

    margin: 0;

    font-size: 28px;

    font-weight: 600;

}


.heading p {

    margin:
        7px 0 0;

    color:
        var(--muted);

    font-size: 13px;

}


/* ALERT */

.alert {

    margin-bottom: 20px;

    padding:
        15px 17px;

    border: 1px solid;

    font-size: 12px;

    line-height: 1.6;

}


.alert.success {

    color:
        var(--success);

    background:
        var(--success-bg);

    border-color:
        #c9ddcd;

}


.alert.error {

    color:
        var(--danger);

    background:
        var(--danger-bg);

    border-color:
        #e3cccc;

}


/* CREDENTIALS */

.credentials {

    margin-bottom: 20px;

    padding: 20px;

    background:
        #f0f3f1;

    border:
        1px solid
        #d6dcd8;

}


.credentials-title {

    font-size: 14px;

    font-weight: 700;

}


.credentials-row {

    margin-top: 10px;

    display: grid;

    grid-template-columns:
        160px 1fr;

    font-size: 12px;

}


.credentials-label {

    color:
        var(--muted);

}


.credentials-value {

    font-weight: 700;

}


/* STATS */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 20px;

}


.stat {

    padding: 20px;

    background:
        white;

    border:
        1px solid
        var(--line);

}


.stat-label {

    color:
        var(--muted);

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

}


.stat-number {

    margin-top: 9px;

    font-size: 30px;

    font-weight: 600;

}


/* PANEL */

.panel {

    margin-bottom: 20px;

    background:
        white;

    border:
        1px solid
        var(--line);

}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        #e7e9e7;

}


.panel-title {

    font-size: 15px;

    font-weight: 600;

}


.panel-description {

    margin-top: 5px;

    color:
        var(--muted);

    font-size: 10px;

}


/* FORM */

.form {

    padding: 22px;

}


.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 17px;

}


.form-group.full {

    grid-column:
        1 / -1;

}


label {

    display: block;

    margin-bottom: 7px;

    color:
        #647278;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

}


select,
input {

    width: 100%;

    height: 43px;

    padding:
        0 11px;

    border:
        1px solid
        #ccd1cf;

    border-radius: 4px;

    background:
        white;

    color:
        var(--text);

    font-family:
        inherit;

    font-size: 12px;

}


select:focus,
input:focus {

    outline: none;

    border-color:
        var(--slate);

}


.help {

    margin-top: 6px;

    color:
        var(--muted);

    font-size: 9px;

}


/* BUTTON */

.btn {

    display: inline-block;

    padding:
        8px 12px;

    border-radius: 4px;

    font-family:
        inherit;

    font-size: 9px;

    font-weight: 600;

    cursor: pointer;

}


.btn-create {

    border: 0;

    background:
        var(--navy);

    color: white;

    padding:
        11px 18px;

}


.btn-create:hover {

    background:
        var(--navy2);

}


.btn-danger {

    background: white;

    color:
        var(--danger);

    border:
        1px solid
        #decaca;

}


.btn-success {

    background: white;

    color:
        var(--success);

    border:
        1px solid
        #cbdccc;

}


.btn-reset {

    background: white;

    color:
        #60717a;

    border:
        1px solid
        #d1d6d4;

}


/* TABLE */

.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th {

    padding:
        13px 15px;

    background:
        #f1f2f0;

    color:
        #657277;

    border-bottom:
        1px solid
        var(--line);

    text-align: left;

    font-size: 10px;

    text-transform: uppercase;

}


td {

    padding:
        14px 15px;

    border-bottom:
        1px solid
        #eceeec;

    font-size: 11px;

}


.person-name {

    font-weight: 600;

}


.person-meta {

    margin-top: 4px;

    color:
        var(--muted);

    font-size: 9px;

}


.badge {

    display: inline-block;

    padding:
        6px 9px;

    border-radius: 4px;

    font-size: 9px;

    font-weight: 700;

}


.badge.active {

    background:
        var(--success-bg);

    color:
        var(--success);

}


.badge.inactive {

    background:
        var(--danger-bg);

    color:
        var(--danger);

}


.role {

    color:
        #617077;

    font-weight: 600;

}


.empty {

    padding:
        45px 20px;

    text-align: center;

    color:
        var(--muted);

    font-size: 11px;

}


/* MOBILE */

@media(max-width:1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media(max-width:750px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

    }


    .main {

        margin-left: 0;

    }


    .nav {

        display: grid;

        grid-template-columns:
            1fr 1fr;

    }


    .content {

        padding:
            22px 15px;

    }


    .form-grid {

        grid-template-columns:
            1fr;

    }


    .form-group.full {

        grid-column:
            auto;

    }

}


@media(max-width:550px) {

    .stats {

        grid-template-columns:
            1fr;

    }


    .heading h2 {

        font-size: 22px;

    }


    .topbar h1 {

        font-size: 18px;

    }

}

</style>

</head>


<body>


<!-- SIDEBAR -->

<aside class="sidebar">


<div class="brand">

    <div class="brand-title">
        HIBS REPORTS
    </div>

    <div class="brand-subtitle">

        HILLTOP INTERNATIONAL<br>
        BRITISH SCHOOL

    </div>

</div>


<div class="nav-label">
    Administration
</div>


<nav class="nav">


<a href="dashboard.php">

    <span class="icon">▦</span>
    Dashboard

</a>


<a href="academic_setup.php">

    <span class="icon">◫</span>
    Academic Setup

</a>


<a href="teacher_assignments.php">

    <span class="icon">⟷</span>
    Teacher Assignments

</a>


<a href="students.php">

    <span class="icon">♙</span>
    Students

</a>


<a href="teachers.php">

    <span class="icon">♙</span>
    Teachers

</a>


<a
    href="accounts.php"
    class="active"
>

    <span class="icon">♙</span>
    Account Management

</a>


<a href="classes.php">

    <span class="icon">□</span>
    Classes

</a>


<a href="subjects.php">

    <span class="icon">◇</span>
    Subjects

</a>


<a href="mark_submissions.php">

    <span class="icon">✓</span>
    Mark Submissions

</a>


<a href="reports.php">

    <span class="icon">▤</span>
    Reports

</a>


<a href="report_approval.php">

    <span class="icon">✓</span>
    Report Approval

</a>


<a href="publish_report.php">

    <span class="icon">↑</span>
    Publish Reports

</a>


<a href="analytics.php">

    <span class="icon">◒</span>
    Analytics

</a>


<a href="database_check.php">

    <span class="icon">◉</span>
    Database Check

</a>


<a href="settings.php">

    <span class="icon">⚙</span>
    Settings

</a>


</nav>


</aside>


<!-- MAIN -->

<main class="main">


<header class="topbar">

    <h1>
        Account Management
    </h1>

</header>


<div class="content">


<div class="heading">

    <h2>
        Student & Teacher Accounts
    </h2>

    <p>
        Create and manage secure login accounts for HIBS students and teachers.
    </p>

</div>


<?php if (
    $success !== ""
): ?>

<div class="alert success">

    <?= h(
        $success
    ) ?>

</div>


<?php if (
    $createdUsername !== ""
): ?>

<div class="credentials">

    <div class="credentials-title">

        New Login Credentials

    </div>


    <div class="credentials-row">

        <div class="credentials-label">
            Account Holder
        </div>

        <div class="credentials-value">

            <?= h(
                $createdPerson
            ) ?>

        </div>

    </div>


    <div class="credentials-row">

        <div class="credentials-label">
            Username
        </div>

        <div class="credentials-value">

            <?= h(
                $createdUsername
            ) ?>

        </div>

    </div>


    <div class="credentials-row">

        <div class="credentials-label">
            Temporary Password
        </div>

        <div class="credentials-value">

            <?= h(
                $createdPassword
            ) ?>

        </div>

    </div>


    <div
        style="
            margin-top:14px;
            color:#657277;
            font-size:10px;
        "
    >

        Give these credentials to the student or teacher securely.
        The password is stored as a secure hash and cannot be retrieved later.

    </div>

</div>

<?php endif; ?>


<?php endif; ?>


<?php if (
    $error !== ""
): ?>

<div class="alert error">

    <?= h(
        $error
    ) ?>

</div>

<?php endif; ?>


<!-- STATISTICS -->

<section class="stats">


<div class="stat">

    <div class="stat-label">
        Student Accounts
    </div>

    <div class="stat-number">
        <?= number_format(
            $studentAccounts
        ) ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Teacher Accounts
    </div>

    <div class="stat-number">
        <?= number_format(
            $teacherAccounts
        ) ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Active Accounts
    </div>

    <div class="stat-number">
        <?= number_format(
            $activeAccounts
        ) ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Inactive Accounts
    </div>

    <div class="stat-number">
        <?= number_format(
            $inactiveAccounts
        ) ?>
    </div>

</div>


</section>


<!-- CREATE ACCOUNT -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">
        Create Login Account
    </div>

    <div class="panel-description">

        Select an existing student or teacher and create their HIBS login.

    </div>

</div>


<form
    method="POST"
    class="form"
>


<input
    type="hidden"
    name="action"
    value="create_account"
>


<div class="form-grid">


<div>

<label>
    Account Type
</label>


<select
    name="person_type"
    id="personType"
    required
>

<option value="">
    Select Account Type
</option>

<option value="student">
    Student
</option>

<option value="teacher">
    Teacher
</option>

</select>

</div>


<div>

<label>
    Person
</label>


<select
    name="person_id"
    id="personId"
    required
>

<option value="">
    Select account type first
</option>

</select>

</div>


<div>

<label>
    Username
</label>


<input
    type="text"
    name="username"
    id="username"
    placeholder="Username"
    required
    minlength="4"
>


<div class="help">

The system will suggest the student's ID or teacher's staff ID when available.

</div>

</div>


<div>

<label>
    Temporary Password
</label>


<input
    type="password"
    name="password"
    minlength="8"
    placeholder="Minimum 8 characters"
    required
>


<div class="help">

Minimum 8 characters.

</div>

</div>


<div class="form-group full">

<button
    type="submit"
    class="btn btn-create"
>

    Create Login Account

</button>

</div>


</div>


</form>


</section>


<!-- EXISTING ACCOUNTS -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Existing Login Accounts

    </div>

    <div class="panel-description">

        Student and teacher accounts currently registered in HIBS Reports.

    </div>

</div>


<div class="table-wrap">


<?php if (
    count($accounts) > 0
): ?>


<table>


<thead>

<tr>

<th>
    Account Holder
</th>

<th>
    Username
</th>

<th>
    Role
</th>

<th>
    Status
</th>

<th>
    Created
</th>

<th>
    Actions
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $accounts as $account
): ?>


<tr>


<td>

<div class="person-name">

    <?= h(
        $account[
            "person_name"
        ]
    ) ?>

</div>


<?php if (
    !empty(
        $account[
            "person_identifier"
        ]
    )
): ?>

<div class="person-meta">

    <?= h(
        $account[
            "person_identifier"
        ]
    ) ?>

</div>

<?php endif; ?>


<?php if (
    !empty(
        $account[
            "class_name"
        ]
    )
): ?>

<div class="person-meta">

    <?= h(
        $account[
            "class_name"
        ]
    ) ?>

</div>

<?php endif; ?>


</td>


<td>

    <?= h(
        $account[
            "username"
        ] ?? ""
    ) ?>

</td>


<td>

<span class="role">

    <?= h(
        ucfirst(
            (string)
            (
                $account[
                    "role"
                ] ?? ""
            )
        )
    ) ?>

</span>

</td>


<td>


<?php if (
    (
        $account[
            "status"
        ] ?? "active"
    )
    ===
    "active"
): ?>

<span class="badge active">

    Active

</span>

<?php else: ?>

<span class="badge inactive">

    Inactive

</span>

<?php endif; ?>


</td>


<td>

<?php

$createdAt =
    $account[
        "created_at"
    ]
    ??
    null;


if (
    $createdAt
) {

    echo h(
        date(
            "d M Y",
            strtotime(
                (string)$createdAt
            )
        )
    );

} else {

    echo "—";
}

?>

</td>


<td>


<?php if (
    (
        $account[
            "status"
        ] ?? "active"
    )
    ===
    "active"
): ?>


<form
    method="POST"
    style="display:inline-block;"
    onsubmit="
        return confirm(
            'Deactivate this account?'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="change_status"
>


<input
    type="hidden"
    name="user_id"
    value="<?= (int)$account["id"] ?>"
>


<input
    type="hidden"
    name="new_status"
    value="inactive"
>


<button
    type="submit"
    class="btn btn-danger"
>

    Deactivate

</button>

</form>


<?php else: ?>


<form
    method="POST"
    style="display:inline-block;"
>

<input
    type="hidden"
    name="action"
    value="change_status"
>


<input
    type="hidden"
    name="user_id"
    value="<?= (int)$account["id"] ?>"
>


<input
    type="hidden"
    name="new_status"
    value="active"
>


<button
    type="submit"
    class="btn btn-success"
>

    Activate

</button>

</form>


<?php endif; ?>


<button
    type="button"
    class="btn btn-reset"
    onclick="
        resetPassword(
            <?= (int)$account["id"] ?>,
            '<?= h(
                $account[
                    "username"
                ] ?? ""
            ) ?>'
        );
    "
>

    Reset Password

</button>


</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>


<?php else: ?>


<div class="empty">

    No student or teacher accounts have been created yet.

</div>


<?php endif; ?>


</div>


</section>


</div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| DATA FROM PHP
|--------------------------------------------------------------------------
*/

const students =
    <?= json_encode(
        $students,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;


const teachers =
    <?= json_encode(
        $teachers,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;


/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const personType =
    document.getElementById(
        "personType"
    );


const personId =
    document.getElementById(
        "personId"
    );


const username =
    document.getElementById(
        "username"
    );


/*
|--------------------------------------------------------------------------
| LOAD PEOPLE
|--------------------------------------------------------------------------
*/

personType.addEventListener(
    "change",
    function () {

        personId.innerHTML =
            '<option value="">Select Person</option>';

        username.value = "";


        let people = [];


        if (
            this.value ===
            "student"
        ) {

            people =
                students;

        } else if (
            this.value ===
            "teacher"
        ) {

            people =
                teachers;

        } else {

            personId.innerHTML =
                '<option value="">Select account type first</option>';

            return;
        }


        people.forEach(
            function (
                person
            ) {

                const option =
                    document.createElement(
                        "option"
                    );


                option.value =
                    person.id;


                let name =
                    "";


                if (
                    person.first_name
                ) {

                    name +=
                        person.first_name;
                }


                if (
                    person.middle_name
                ) {

                    name +=
                        " "
                        +
                        person.middle_name;
                }


                if (
                    person.last_name
                ) {

                    name +=
                        " "
                        +
                        person.last_name;
                }


                if (
                    !name.trim()
                ) {

                    name =
                        person.name
                        ??
                        person.full_name
                        ??
                        (
                            "Person #"
                            +
                            person.id
                        );
                }


                name =
                    name.trim();


                if (
                    this.value ===
                    "student"
                    &&
                    person.student_id
                ) {

                    name +=
                        " — "
                        +
                        person.student_id;
                }


                if (
                    this.value ===
                    "teacher"
                ) {

                    const staffId =
                        person.employee_id
                        ??
                        person.staff_id
                        ??
                        person.teacher_id
                        ??
                        "";


                    if (
                        staffId
                    ) {

                        name +=
                            " — "
                            +
                            staffId;
                    }
                }


                option.textContent =
                    name;


                personId.appendChild(
                    option
                );

            }.bind(this)
        );

    }
);


/*
|--------------------------------------------------------------------------
| AUTO USERNAME
|--------------------------------------------------------------------------
*/

personId.addEventListener(
    "change",
    function () {

        const type =
            personType.value;


        const id =
            parseInt(
                this.value,
                10
            );


        if (
            !id
        ) {

            return;
        }


        const people =
            type === "student"
            ? students
            : teachers;


        const person =
            people.find(
                function (
                    item
                ) {

                    return parseInt(
                        item.id,
                        10
                    ) === id;

                }
            );


        if (
            !person
        ) {

            return;
        }


        if (
            type ===
            "student"
            &&
            person.student_id
        ) {

            username.value =
                person.student_id;

            return;
        }


        if (
            type ===
            "teacher"
        ) {

            const staffId =
                person.employee_id
                ??
                person.staff_id
                ??
                person.teacher_id
                ??
                "";


            if (
                staffId
            ) {

                username.value =
                    staffId;

                return;
            }


            const first =
                (
                    person.first_name
                    ??
                    ""
                )
                .trim()
                .toLowerCase();


            const last =
                (
                    person.last_name
                    ??
                    ""
                )
                .trim()
                .toLowerCase();


            if (
                first ||
                last
            ) {

                username.value =
                    (
                        first
                        +
                        "."
                        +
                        last
                    )
                    .replace(
                        /\s+/g,
                        ""
                    );
            }
        }

    }
);


/*
|--------------------------------------------------------------------------
| RESET PASSWORD
|--------------------------------------------------------------------------
*/

function resetPassword(
    userId,
    username
) {

    const password =
        prompt(
            "Enter a new password for "
            +
            username
            +
            ":\n\nMinimum 8 characters."
        );


    if (
        password === null
    ) {

        return;
    }


    if (
        password.length < 8
    ) {

        alert(
            "Password must contain at least 8 characters."
        );

        return;
    }


    const form =
        document.createElement(
            "form"
        );


    form.method =
        "POST";


    const action =
        document.createElement(
            "input"
        );

    action.type =
        "hidden";

    action.name =
        "action";

    action.value =
        "reset_password";


    const id =
        document.createElement(
            "input"
        );

    id.type =
        "hidden";

    id.name =
        "user_id";

    id.value =
        userId;


    const pass =
        document.createElement(
            "input"
        );

    pass.type =
        "hidden";

    pass.name =
        "new_password";

    pass.value =
        password;


    form.appendChild(
        action
    );

    form.appendChild(
        id
    );

    form.appendChild(
        pass
    );


    document.body.appendChild(
        form
    );


    form.submit();
}

</script>


</body>

</html>
