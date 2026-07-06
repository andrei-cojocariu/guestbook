<h2>Pleasee fill in the fallowing form</h2>

<?php if (isset($valid) && $valid === true): ?>

<div class="alert alert-success alert-dismissable">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <strong>Success!</strong> Your message has been processed.
</div>

<?php elseif (isset($valid) && $valid === false): ?>

<div class="alert alert-danger alert-dismissable">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <strong>Error!</strong> Something went wrong.
</div>

<?php endif ?>

<div class="grids">

<?php
    // CI3-era inline error markup, preserved verbatim for the
    // characterization contract ("help-block has-error" delimiters).
    $fieldError = static function (array $errors, string $field): string {
        return isset($errors[$field])
            ? '<span id="textfield-error" class="help-block has-error">' . esc($errors[$field]) . '</span>'
            : '';
    };

    echo form_open('Guestbook/create', ['class' => 'form-validate', 'id' => 'test']);
?>
        <div class="row">

            <div class="col-sm-6">
                <div class="form-group <?php if (isset($errors['name'])) { echo 'has-error'; } ?>">
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="glyphicon-user"></i>
                        </span>
<?php
    echo form_input([
        'name'                => 'name',
        'placeholder'         => 'Your Name',
        'class'               => 'form-control',
        'data-rule-required'  => 'true',
        'data-rule-minlength' => '3',
        'value'               => set_value('name'),
    ]);
    echo $fieldError($errors ?? [], 'name');
?>
                    </div>
                </div>

                <div class="form-group <?php if (isset($errors['email'])) { echo 'has-error'; } ?>">
                    <div class="input-group">
                        <span class="input-group-addon">@</span>
<?php
    echo form_input([
        'name'                => 'email',
        'placeholder'         => 'Email address',
        'class'               => 'form-control',
        'data-rule-required'  => 'true',
        'data-rule-email'     => 'true',
        'value'               => set_value('email'),
    ]);
    echo $fieldError($errors ?? [], 'email');
?>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group <?php if (isset($errors['message'])) { echo 'has-error'; } ?>">
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-edit"></i>
                        </span>
<?php
    echo form_textarea([
        'name'                => 'message',
        'placeholder'         => 'Your Message',
        'class'               => 'form-control',
        'rows'                => '3',
        'data-rule-required'  => 'true',
        'data-rule-minlength' => '5',
        'value'               => set_value('message'),
    ]);
    echo $fieldError($errors ?? [], 'message');
?>
                    </div>
                </div>
            </div>

        </div>

        <div class="row">
            <div class="submit">
<?php
    echo form_submit([
        'value' => 'Submit',
        'class' => 'btn btn-primary',
    ]);
?>
            </div>
        </div>

<?= form_close() ?>

</div>
