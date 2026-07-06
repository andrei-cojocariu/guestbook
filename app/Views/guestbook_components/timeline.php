
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
                            <?= date('d-m-y', strtotime($message['received_on'])) ?><br>
                            <?= date('h:i a', strtotime($message['received_on'])) ?>
                        </div>
                    </div>
                    <div class="activity">
                        <div class="user">
                            <a href="#"><?= esc($message['name']) ?></a>
                            <span>(<?= esc($message['email']) ?>)</span>
                        </div>
                        <p>
                            <?= esc($message['message']) ?>
                        </p>
                    </div>
                </div>
                <div class="line"></div>
            </li>

<?php endforeach ?>
        </ul>
    </div>
</div>
