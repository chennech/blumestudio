<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Bump_to_3_2_9 extends CI_Migration {
	
    public function up()
	{ 
        Settings::setVersion('3.2.9');
		
		
		
    }
    
    public function down()
	{
        Settings::setVersion('3.2.8');
    }
}