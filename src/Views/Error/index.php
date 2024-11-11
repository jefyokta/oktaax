<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <style>
        body {
            background-color: #020617;
            color: #f5f6fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            height: 100vh;
        }

        * {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
            color: aliceblue;
        }

        .error-container {
            background-color: #3336;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: left;
            width: 80%;
            max-width: 800px;
            overflow: auto;
        }

        .error-title {
            font-size: 40px;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: 20px;

        }

        .error-message {
            font-size: 18px;
            color: #e74c3c;
            margin-bottom: 30px;
        }

        .debug-info {
            font-size: 14px;
            color: #f5f6fa;
            background-color: #1e272e;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow-x: auto;
        }


        .error-icon {
            font-size: 70px;
            color: #e74c3c;
            margin-bottom: 20px;
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <h1>X-Powered-By : Oktaax</h1>
        <div class="error-icon"> Error!</div>
        <div class="error-message">
            <?= $error->getMessage(); ?> </div>
        <div class="debug-info">
            <?= $code; ?>
        </div>
        <div class="" style="margin-bottom: 20px;">

            <strong>Stack Trace:</strong>
            <div>
               - at <em> <?= $error->getFile() . " : " . $error->getLine(); ?> </em>
            </div>
        </div>

        <ul class="debug-info" style="white-space: wrap!important;padding:2em">
            <?php foreach ($req->server as $key => $val): ?>
                <li><?= $key; ?>:<?= $val; ?></li>
            <?php endforeach; ?>
        </ul>

    </div>
</body>

</html>