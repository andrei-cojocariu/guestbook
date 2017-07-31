
<div class="box box-color box-bordered">
    <div class="box-title">
        <h3>
            <i class="fa fa-bars"></i>
            Previous Messages
        </h3>
    </div>

    <div class="box-content nopadding">

        <ul class="timeline">
            
<?php foreach ($messages as $message): ?>       
            
            <li>
                <div class="timeline-content">
                    <div class="left">
                        <div class="icon green">
                            <i class="fa fa-comment"></i>
                        </div>
                        <div class="date">
                            <?php echo date('d-m-y', time($message['received_on'])); ?><br>
                            <?php echo date('h:i a', time($message['received_on'])); ?>
                        </div>   
                    </div>
                    <div class="activity">
                        <div class="user">
                            <a href="#"><?php echo $message['name']; ?></a>
                            <span>(<?php echo $message['email']; ?>)</span>
                        </div>
                        <p>
                            <?php echo $message['message']; ?>
                        </p>
                    </div>
                </div>
                <div class="line"></div>
            </li>
            
<?php endforeach; ?>
        </ul>
    </div>
</div> 