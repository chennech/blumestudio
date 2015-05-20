<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Fix_dompdf_cache extends CI_Migration {

    function up() {
        $this->load->model("upgrade/update_system_m");
        $old_filename = APPPATH."libraries/dompdf/lib/fonts/dompdf_font_family_cache.php";
        $new_filename = APPPATH."libraries/dompdf/lib/fonts/dompdf_font_family_cache.bak.php";

        if (file_exists($old_filename)) {
            $this->update_system_m->set_file_contents($new_filename, file_get_contents($old_filename));
            $this->update_system_m->delete($old_filename);
        }
    }

    function down() {

    }

}
