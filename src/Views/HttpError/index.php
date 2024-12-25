<!DOCTYPE html>
<html lang="en">

<head>
    <style>
        * {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
            color: aliceblue;
        }

        .container {
            height: 100vh;
            width: 100vw;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #020617;
        }

        .text-center {
            text-align: center;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? "Not Found"; ?></title>
</head>

<body class="container">

    <div class="container">
        <div>
            <h1 class="text-center"><?= $status ?? 404; ?></h1>
            <p class="text-center" style="color: #cbd5e1;opacity:.5"><?= $title ?? "Not Found"; ?></p>
            <div style="margin-top: 20px;">
                <p class="text-center" style="color: #cbd5e1;opacity:.2">Powered By Oktaax</p>
            </div>
        </div>
    </div>

</body>

</html>