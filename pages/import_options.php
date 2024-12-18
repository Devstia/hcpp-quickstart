<?php require( 'header.php' ); ?>
<?php
    // Validate the job_id
    $job_id = $_GET['job_id'];
    if ( $hcpp->quickstart->is_job_valid( $job_id ) === false ) {
        header( 'Location: ?quickstart=main' );
        exit;
    }

    // Locate the import file
    $import_file = $hcpp->quickstart->peek_job_data( $job_id, 'import_file' );
    if ( false == file_exists( $import_file ) ) {
        header( 'Location: ?quickstart=main' );
        exit;
    }
    
    // Start import file processing by job id
    $hcpp->quickstart->import_file( $job_id );
?>
<div class="toolbar" style="z-index:100;position:relative;">
    <div class="toolbar-inner">
        <div class="toolbar-buttons">
            <a href="#" class="button button-secondary button-back js-button-back" id="back-button">
                <i tabindex="300" class="fas fa-stop-circle icon-red"></i>Cancel			
            </a>
        </div>
        <div class="toolbar-buttons">
            <a href="?quickstart=import" class="button disabled" id="continue-button">
                <i tabindex="200" class="fas fa-arrow-right icon-blue"></i>Continue
            </a>         
        </div>
    </div>
</div>
<div class="body-reset container">
    <div class="quickstart qs_import_options">
        <h1>Import Options</h1>
        <legend id="status">Please wait. Decompressing and analyzing files.</legend>
        <div id="options"></div>
    </div>
</div>
<script>
    (function($){
        $(function() {

            // Var-safe title function
            function titleToVarName(str) {
                str = str.toLowerCase(); // Convert all characters to lowercase
                str = str.replace(/[^a-z0-9\s]/g, ''); // Remove all non-alphanumeric characters except spaces
                str = str.replace(/\s+/g, '_'); // Replace one or more spaces with underscores
                str = str.replace(/_([a-z])/g, function (g) { return g[1].toUpperCase(); }); // Convert underscores to camelCase
                return str;
            }

            // Check the import key every 6 seconds
            var import_int = setInterval( () => {
                $.ajax({
                    url: '../../pluginable.php?load=quickstart&action=import_status&job_id=<?php echo $job_id; ?>',
                    type: 'GET',
                    success: function( data ) {
                        try {
                            data = JSON.parse( data );
                        } catch( e ) {
                            $('#error').html( '<p>Error parsing JSON: ' + e + '</p>');
                            $('#error').show();
                        }
                        if ( data.status == 'running' ) return;
                        if ( data.status == 'finished' ) {
                            const manifest = data.manifest;
                            let domain = manifest.domain;
                            let aliases = manifest.aliases;

                            // Suggest .dev.pw or .dev.cc accordingly for popular TLDs
                            let pop_country_tlds = ['.au', '.ca', '.cn', '.de', '.fr', '.in', '.it', '.jp', '.nz', '.uk', '.us', '.za'];
                            let pop_top_tlds = ['.com', '.net', '.org', '.gov', '.edu', '.govt', '.co'];
                            let popular_tlds = ['.gc.ca'];
                            pop_country_tlds.forEach( (country) => {
                                pop_top_tlds.forEach( (top) => {
                                    popular_tlds.push( top + country );
                                });
                            });
                            popular_tlds.push( '.com', '.net', '.org', '.info', '.biz', '.app', '.tech', '.online', '.store', '.dev', '.me', '.co', '.io' );
                            pop_country_tlds.forEach( (country) => {
                                popular_tlds.push( country );
                            });

                            <?php if ( isset( $hcpp->dev_pw ) ) { ?>
                            let suggest_tld = '.dev.pw';
                            <?php }elseif ( isset( $hcpp->dev_cc ) ) { ?>
                            let suggest_tld = '.dev.cc';
                            <?php } ?>
                            for (const tld of popular_tlds) {
                                if ( domain.endsWith( tld ) ) {
                                    domain = domain.replace( tld, suggest_tld );
                                    break;
                                }
                            }
                            aliases.forEach( (alias, i) => {
                                for (const tld of popular_tlds) {
                                    if ( alias.endsWith( tld ) ) {
                                        aliases[i] = alias.replace( tld, suggest_tld );
                                        break;
                                    }
                                }
                            });
                            aliases = manifest.aliases.join("\n");
                            $('#status').html(data.message);

                            // Create form to customize domain/aliases 
                            let html = `<form id="import_now" method="POST" action="?quickstart=import_now&job_id=<?php echo $job_id; ?>">
                                        <div class="u-mb10">
                                            <label for="v_domain" class="form-label">Domain</label>
                                            <input type="text" class="form-control" name="v_domain" id="v_domain" value="${domain}" required="">
                                        </div>`;
                            if (aliases != '') {
                                html += `<div class="u-mb10">
                                            <label for="v_aliases" class="form-label">Aliases</label>
                                            <textarea class="form-control" name="v_aliases" id="v_aliases">${aliases}</textarea>
                                        </div>`;
                            }
                            
                            // Create form for advanced options
                            if (manifest.export_adv_options.length > 0) {
                                manifest.export_adv_options.forEach( (option) => {
                                    if (option.label == '') {
                                        html += `<div class="u-mb10">` + option.value + `</div>`;
                                        return;
                                    }
                                    let labelVar = titleToVarName(option.label);
                                    html += `<div class="u-mb10">
                                                <label for="eao_${labelVar}" class="form-label">${option.label}</label>`;
                                    if (option.value.indexOf("\n") > -1) {
                                        if (option.value.indexOf("|") > -1) {
                                            html += `<select class="form-select" name="eao_${labelVar}" id="eao_${labelVar}">`;
                                            option.value.split("\n").forEach( (opt) => {
                                                const optArr = opt.split("|");
                                                html += `<option value="${optArr[1]}">${optArr[0]}</option>`;
                                            });
                                            html += `</select>`;
                                        }else{
                                            const h = option.value.split("\n").length * 1.75;
                                            html += `<textarea class="form-control" name="eao_${labelVar}" id="eao_${labelVar}" style="min-height:${h}rem;">${option.value}</textarea>`;
                                        }
                                    }else{
                                        html += `<input type="text" class="form-control" name="eao_${labelVar}" id="eao_${labelVar}" value="${option.value}">`;
                                    }
                                    html += `<input type="hidden" name="eao_${labelVar}_ref_files" value="${option.ref_files}">
                                        </div>`;
                                });
                            }


                            html += '</form>';
                            $('#options').html(html);
                            setTimeout(()=>{
                                $('#v_domain').focus().select();
                            }, 500);
                            $('#continue-button').attr('href', '#');
                            $('#continue-button').on('click', (e) => {
                                if ($('#continue-button').attr('href') == '#') {
                                    e.preventDefault();
                                    $('#import_now').submit();
                                }
                            });
                        } else {
                            $('#continue-button').attr('href', '?quickstart=main');
                            if ( data.status == 'error' ) {
                                $('#status').html(data.message);
                            }else{
                                $('#status').html( data.message || 'An unknown error occurred. Please try again.');
                            }
                        }
                        $('#continue-button').removeClass('disabled');
                        $('.spinner-overlay').removeClass('active');
                        clearInterval( import_int );
                    }
                });
            }, 6000);
            $('.spinner-overlay').addClass('active');

            // Cancel the import
            $('#back-button').on('click', (e) => {
                clearInterval( import_int );
                e.preventDefault();
                $.ajax({
                    url: '../../pluginable.php?load=quickstart&action=cancel_job&job_id=<?php echo $job_id; ?>',
                    type: 'GET',
                    success: function( data ) {
                        $('#status').html( 'Import cancelled. Click continue.');
                        $('#back-button').hide();
                        $('#options').hide();
                        $('#continue-button').removeClass('disabled');
                        $('#continue-button').attr('href', '?quickstart=main');
                        $('.spinner-overlay').removeClass('active');
                    }
                });
            });
        });
    })(jQuery);
</script>