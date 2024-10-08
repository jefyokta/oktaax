<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <h1><?php echo e('jefyoktas'); ?></h1>
    <form action="" method="POST">
        <?php echo \Oktaax\Blade\BladeDirectives::methodField("put"); ?>
        <input type="text" name="name" value="jefyokta">
        <button type="submit"></button>
    </form>
</body>

</html>
<?php /**PATH /Applications/jepi.okta/oktaax/views/index.blade.php ENDPATH**/ ?>