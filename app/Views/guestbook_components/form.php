<h2>Please fill in the following form</h2>

<?php if (isset($valid) && $valid === true): ?>

<div class="alert alert-success alert-dismissable" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">×</button>
    <strong>Success!</strong> Your message has been processed.
</div>

<?php elseif (isset($valid) && $valid === false): ?>

<div class="alert alert-danger alert-dismissable" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">×</button>
    <strong>Error!</strong> Your message could not be saved. Please try again.
</div>

<?php endif ?>

<div class="grids">

<?php
    // CI3-era inline error markup, preserved verbatim for the
    // characterization contract ("help-block has-error" delimiters).
    $fieldError = static function (array $errors, string $field): string {
        return isset($errors[$field])
            ? '<span id="' . $field . '-error" role="alert" class="help-block has-error">' . esc($errors[$field]) . '</span>'
            : '';
    };

    echo form_open('Guestbook/create', ['class' => 'form-validate', 'id' => 'test']);
?>
        <div class="row">

            <div class="col-sm-6">
                <div class="form-group <?php if (isset($errors['name'])) { echo 'has-error'; } ?>">
                    <label for="name" class="sr-only">Your Name</label>
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="glyphicon-user" aria-hidden="true"></i>
                        </span>
<?php
    echo form_input([
        'name'                => 'name',
        'id'                  => 'name',
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
                    <label for="email" class="sr-only">Email address</label>
                    <div class="input-group">
                        <span class="input-group-addon" aria-hidden="true">@</span>
<?php
    echo form_input([
        'name'                => 'email',
        'id'                  => 'email',
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
                    <label for="message" class="sr-only">Your Message</label>
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-edit" aria-hidden="true"></i>
                        </span>
<?php
    echo form_textarea([
        'name'                => 'message',
        'id'                  => 'message',
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
