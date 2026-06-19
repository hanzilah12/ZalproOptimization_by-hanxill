<?php
$strict_username = isset($user->username) ? trim($user->username) : '';
?>

<div class="row" style="margin-top:25px;">
    <div class="col-md-12">
        <div class="x_panel" style="border:1px solid #eedfdf;">
            <div class="x_title">
                <h2>
                    <i class="fa fa-terminal"></i>
                    Isolated Profile Live Log —
                    <strong><?php echo htmlspecialchars($strict_username, ENT_QUOTES, 'UTF-8'); ?></strong>
                </h2>
                <div class="clearfix"></div>
            </div>

            <div class="x_content" style="padding:0;">
                <div id="auth-log-native-container" style="height:220px;">
                    <p style="padding:15px;text-align:center;color:#999;">
                        Parsing profile log session...
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    var finalUser = "<?php echo htmlspecialchars($strict_username, ENT_QUOTES, 'UTF-8'); ?>";

    if (finalUser) {
        $("#auth-log-native-container").html(
            '<iframe ' +
            'src="/userlog.php?username=' + encodeURIComponent(finalUser) + '&filter=strict" ' +
            'style="width:100%;height:100%;border:none;background:#fff;">' +
            '</iframe>'
        );
    }

});
</script>
