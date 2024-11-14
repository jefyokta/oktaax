<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/js/app.js', 'resources/css/app.css'])

    <title>Oktaax</title>
</head>

<body class="bg-slate-900">
    <div class="flex w-full justify-center">
        <h1 class="text-white p-5 text-4xl">
            Hi {{ $name }}
        </h1>
    </div>
    <div class="flex justify-center p-10">

        <div id="markdown" class="text-slate-100 bg-gray-950 p-10"></div>
    </div>
    <div id="content"></div>



</body>


</html>
