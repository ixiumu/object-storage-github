<div class="wrap">

<h1>云存储设置（Github）</h1>

<?php if($messages): ?>
    <div id="message" class="notice notice-success is-dismissible"><?php foreach($messages as $msg): ?><p><?php echo $msg; ?></p><?php endforeach; ?></div>
<?php endif; ?>

<p>自动将文件上传至云存储，插件分为多个平台的版本。</p>

<h2>授权设置</h2>

<form action="options.php" method="post">
    <?php settings_fields('storage-options'); ?>
    <?php do_settings_sections('storage-options'); ?>
    <table class="form-table">
        <tr>
            <th>GitHub ID</th>
            <td>
                <input name="storage-owner" type="text" size="15" value="<?php echo esc_attr( get_option('storage-owner') ); ?>" class="regular-text code"/>
            </td>
        </tr>
        <tr>
            <th>仓库（Repository）</th>
            <td>
                <input name="storage-repo" type="text" size="15" value="<?php echo esc_attr( get_option('storage-repo') ); ?>"  class="regular-text code"/>
            </td>
        </tr>
        <tr>
            <th>分支（Branch）</th>
            <td>
                <input name="storage-branch" type="text" size="15" value="<?php echo esc_attr( get_option('storage-branch') ); ?>" class="regular-text code"/>
            </td>
        </tr>
        <tr>
            <th>Access tokens</th>
            <td>
                <input name="storage-token" type="password" size="15" value="<?php echo esc_attr( get_option('storage-token') ); ?>" class="regular-text code"/>
                <p class="description">私有存储需要订阅后链接才可公开访问，请将存储空间设置为<strong>公开</strong>。</p>
            </td>
        </tr>
    </table>
	
	<?php submit_button('测试连接', 'button-storage-check', 'check') ?>

    <h3>拓展选项</h3>

    <table class="form-table">
        <tr>
            <th>文件类型拓展名:</th>
            <td>
                <input name="storage-extensions" type="text" size="15" value="<?php echo esc_attr( get_option('storage-extensions') ); ?>" class="regular-text code"/>
                <p class="description">设置自动同步到云存储的文件类型，例：<code>png,jpg,gif,mov,wmv</code>，留空或者设置为<code>*</code>则会同步所有文件。</p>
            </td>
        </tr>
        <tr>
            <th>访问路径:</th>
            <td>
                <input name="storage-baseurl" type="text" size="15" value="<?php echo esc_attr( get_option('storage-baseurl') ); ?>" class="regular-text code"
				placeholder="https://raw.githubusercontent.com/..." />
                <p class="description">默认为:<code>https://raw.githubusercontent.com/[userid]/[repository]/[branch]</code>，结尾不需要加<code>/</code>。</p>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <input id="delobject" type="checkbox" name="storage-delobject" value="1" <?php checked(get_option('storage-delobject'),1); ?> />
                <label for="delobject">删除库文件时，从远程中删除对象。</label>
            </td>
        </tr>
    </table>

    <?php submit_button(); ?>

</form>

<hr />
<h2>同步</h2>
<form method="post">
	<p>同步所有文件到云存储，注意这将需要很长时间。</p>
	<?php submit_button('立即同步', 'button-secondary', 'resync', true, 'disabled=true') ?>
</form>

</div>

<script type="text/javascript">
jQuery(function($){

    $('.button-storage-check').click(function(){
		$(this).attr('disabled','true');
        $.post(
            ajaxurl,
            $('form:first').serialize().replace('action=update', 'action=storage_connect_test'),
            function (response) {
				$('.button-storage-check').removeAttr('disabled');
                var notice_class = (response["is_error"]) ? 'error' : 'notice-success';
                $('h1').after('<div id="message" class="notice '+notice_class+' is-dismissible"><p>'+response["message"]+'</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">忽略此通知。</span></button></div>');
                // fix wp notice-dismiss
                var b = $('#message');
                b.find('.notice-dismiss').on("click.wp-dismiss-notice",function(a){a.preventDefault(),b.fadeTo(100,0,function(){b.slideUp(100,function(){b.remove()})})});
              },
            'json'
        );
        return false;
    });

});
</script>
