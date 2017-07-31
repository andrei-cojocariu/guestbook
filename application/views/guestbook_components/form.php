           
<h2>Pleasee fill in the fallowing form</h2>

<div class="grids">

    <form action="<?php echo site_url(); ?>" method='get' class='form-validate' id="test">

        <div class="row"> 

            <div class="col-sm-6">                            
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="glyphicon-user"></i>
                        </span>
                        <input type="text" name='name' placeholder="Your Name" class='form-control' data-rule-required="true" data-rule-minlength="3" data-rule-digits="false">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon">@</span>
                        <input type="text" name="email" placeholder="Email address" class='form-control' data-rule-required="true" data-rule-email="true">
                    </div>
                </div>
            </div>                  

            <div class="col-sm-6">
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon">                                    
                            <i class="fa fa-edit"></i>
                        </span>
                        <textarea name="message" placeholder="Your Message" class='form-control' data-rule-required="true" data-rule-minlength="5"></textarea>

                    </div>
                </div>
            </div>     

        </div>

        <div class="row">
            <div class="submit">
                <input type="submit" value="Submit" class='btn btn-primary'>
            </div>
        </div>

    </form>

</div>    
