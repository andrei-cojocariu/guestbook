<!doctype html>
<html>
<head>

<?= view('template/metadata') ?>

<?= view('template/css') ?>

<?= view('template/js') ?>

</head>

<body class='login'>
    <div class="wrapper">
        <h1>
            <a href="<?= site_url() ?>">
                <img src="<?= base_url() ?>img/logo-big.png" alt="Guest Book logo" class='retina-ready' width="59" height="49">Guest Book</a>
        </h1>
        <div class="login-body">

<?= view('guestbook_components/form', [
    'valid'  => $valid ?? null,
    'errors' => $errors ?? [],
]) ?>

<?php if (! empty($messages)): ?>
<?= view('guestbook_components/timeline', ['messages' => $messages]) ?>
<?php else: ?>
            <br>
<?php endif ?>

        </div>
    </div>
</body>

</html>
