<!doctype html>
<html>
<head>
    
<?php $this->load->view('template/metadata'); ?>
    
<?php $this->load->view('template/css'); ?>
    
<?php $this->load->view('template/js'); ?>
    
</head>

<body class='login'>
    <div class="wrapper">
        <h1>
            <a href="<?php echo site_url(); ?>">
                <img src="<?php echo base_url(); ?>img/logo-big.png" alt="" class='retina-ready' width="59" height="49">Guest Book</a>
        </h1>
        <div class="login-body">
            
<?php $this->load->view('guestbook_components/form'); ?>
            
<?php $this->load->view('guestbook_components/timeline'); ?>            
                  
        </div>
    </div>
</body>

</html>
