           
<h2>Pleasee fill in the fallowing form</h2>

<?php
    if (isset($valid) && $valid === true) {
?>

<div class="alert alert-success alert-dismissable">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <strong>Success!</strong> Your message has been processed.
</div>

<?php
    } elseif (isset($valid) && $valid === false) {
?>

<div class="alert alert-danger alert-dismissable">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <strong>Error!</strong> Something went wrong.
</div>

<?php } ?>

<div class="grids">

<?php 
    $attributes = array('class' => 'form-validate', 'id' => 'test');
    echo form_open('Guestbook/create',$attributes);
?>
        <div class="row"> 

            <div class="col-sm-6">                            
                <div class="form-group <?php if (form_error('name')) { echo "has-error"; } ?>">
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="glyphicon-user"></i>
                        </span>
<?php
    $input_data = array(
            'name'                => 'name',
            'placeholder'         => 'Your Name',
            'class'               => 'form-control',
            'data-rule-required'  => 'true',    
            'data-rule-minlength' => '3',               
    );
    echo form_input($input_data);
    echo form_error('name');
?>
                    </div>
                </div>

                <div class="form-group <?php if (form_error('email')) { echo "has-error"; } ?>">
                    <div class="input-group">
                        <span class="input-group-addon">@</span>
<?php
    $input_data = array(
            'name'                => 'email',
            'placeholder'         => 'Email address',
            'class'               => 'form-control',
            'data-rule-required'  => 'true',    
            'data-rule-email'     => 'true',               
    );
    echo form_input($input_data);    
    echo form_error('email');
?>
                    </div>
                </div>
            </div>                  
            <div class="col-sm-6">
                <div class="form-group <?php if (form_error('message')) { echo "has-error"; } ?>">
                    <div class="input-group">
                        <span class="input-group-addon">                                    
                            <i class="fa fa-edit"></i>
                        </span>
<?php
    $input_data = array(
            'name'                => 'message',
            'placeholder'         => 'Your Message',
            'class'               => 'form-control',
            'rows'                => '3',
            'data-rule-required'  => 'true',    
            'data-rule-minlength' => '5',             
    );
    echo form_textarea($input_data);    
    echo form_error('message');
?>
                    </div>
                </div>
            </div>     

        </div>

        <div class="row">
            <div class="submit">
<?php
    $input_data = array(
            'value' => 'Submit',
            'class' => 'btn btn-primary',       
    );
    echo form_submit($input_data);
?>
            </div>
        </div>

<?php echo form_close(); ?>
    
</div>    
