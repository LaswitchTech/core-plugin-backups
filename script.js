//
//   Core Framework - Script file
//
//   @license    MIT (https://mit-license.org/)
//   @author     Louis Ouellet <louis@laswitchtech.com>
//

const BackupModalCreate = function(dt = null){

    // Create a modal
    builder.Component(
        "modal",
        null,
        {
            onEnter: false,
            destroy: true,
            icon: "file-zip",
            title: builder.Locale.get("Starting Backup"),
            body: builder.Locale.get("We are about to create a secure backup of your data. This process may take a few minutes, depending on the size of your data. Please do not close this window or navigate away from this page until the backup is complete."),
            cancel: false,
            submit: true,
            callback: {
                submit: function(element,modal){

                    // Create a spinner animate-rotate
                    var spinner = $(document.createElement('div')).attr({
                        "class": "animate-rotate rounded-circle border border-secondary border-4 d-none",
                        "style": "width: 96px; height: 96px; border-top-color: var(--bs-primary)!important;",
                    }).appendTo(element);

                    // Hide the dialog
                    element.dialog.addClass('opacity-0');

                    // Setup a spinner while waiting for the modal to be submitted
                    setTimeout(() => {

                        // Hide the dialog
                        element.dialog.hide();

                        // Add flex to the modal
                        element.addClass('d-flex align-items-center justify-content-center');

                        // Show the spinner
                        spinner.removeClass('d-none');

                        // CSRF Token
                        var data = {};
                        data[CSRF_KEY] = CSRF_TOKEN;

                        // AJAX Request
                        $.ajax({
                            url: '/endpoint.php/backups/init',
                            type: 'POST',dataType: 'json',
                            data: data,
                            success: function(response) {

                                // Update the CSRF
                                CSRF_KEY = response.CSRF.key;
                                CSRF_TOKEN = response.CSRF.token;

                                // Add the item from the list
                                if(dt){
                                    dt.row.add({
                                        "name": response.name,
                                        "path": response.path,
                                        "size": response.size,
                                        "date": response.date,
                                        "hash": response.hash,
                                    }).draw();
                                }

                                // Hide the modal
                                modal.hide();
                            }
                        });
                    }, 300);
                },
            },
        },
        function(modal,component){

            // Save the component
            const componentModal = component;

            // Style the modal
            component.header.addClass('text-bg-success');
            component.footer.submit.addClass('btn-success').removeClass('btn-link').attr({
                "style": "border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;",
            }).text(builder.Locale.get('Start'));
            component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-play me-1').prependTo(component.footer.submit);

            // Open the modal
            modal.show();
        },
    );
};
const BackupModalDelete = function(backups, dt = null){

    // Check if the backups is an array
    if(!Array.isArray(backups)){
        backups = [backups];
    }

    // Check if the uuids is empty
    if(backups.length == 0){
        builder.Toast.add(
            {
                icon: "exclamation-diamond",
                title: builder.Locale.get("Error"),
                body: builder.Locale.get("No backups selected."),
                color: "danger"
            }
        );
        return;
    }

    // Create a modal
    builder.Component(
        "modal",
        null,
        {
            onEnter: false,
            destroy: true,
            icon: "trash",
            title: builder.Locale.get("Are you sure?"),
            body: builder.Locale.get("You are about to delete these backups. This action cannot be undone. Are you sure you want to proceed?"),
            cancel: false,
            submit: true,
            callback: {
                submit: function(element,modal){

                    // Create a spinner animate-rotate
                    var spinner = $(document.createElement('div')).attr({
                        "class": "animate-rotate rounded-circle border border-secondary border-4 d-none",
                        "style": "width: 96px; height: 96px; border-top-color: var(--bs-primary)!important;",
                    }).appendTo(element);

                    // Hide the dialog
                    element.dialog.addClass('opacity-0');

                    // Setup a spinner while waiting for the modal to be submitted
                    setTimeout(() => {

                        // Hide the dialog
                        element.dialog.hide();

                        // Add flex to the modal
                        element.addClass('d-flex align-items-center justify-content-center');

                        // Show the spinner
                        spinner.removeClass('d-none');

                        // Initialize the counter
                        var counter = 0;
                        var total = backups.length;

                        // Loop through the backups
                        backups.forEach(function(backup){

                            // AJAX Request
                            $.ajax({
                                url: '/endpoint.php/backups/delete?uuid=' + backup.name,
                                type: 'GET',dataType: 'json',
                                success: function(response) {

                                    // Remove the item from the list
                                    if(dt){
                                        dt.row(function(idx, data, node) {
                                            return data.name == backup.name;
                                        }).remove().draw();
                                    }

                                    // Increment the counter
                                    counter++;

                                    // Check if all the items have been deleted
                                    if(counter == total){

                                        // Hide the modal
                                        modal.hide();
                                    }
                                }
                            });
                        });
                    }, 300);
                },
            },
        },
        function(modal,component){

            // Save the component
            const componentModal = component;

            // Style the modal
            component.header.addClass('text-bg-danger');
            component.footer.submit.addClass('btn-danger').removeClass('btn-link').attr({
                "style": "border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;",
            }).text(builder.Locale.get('Delete'));
            component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-trash me-1').prependTo(component.footer.submit);

            // Open the modal
            modal.show();
        },
    );
};
const BackupModalRestore = function(uuid){

    // Create a modal
    builder.Component(
        "modal",
        null,
        {
            onEnter: false,
            destroy: true,
            icon: "arrow-counterclockwise",
            title: builder.Locale.get("Restore Backup"),
            body: builder.Locale.get("We are about to restore a backup. This process may take a few minutes, depending on the size of your data. Please do not close this window or navigate away from this page until the restore is complete."),
            cancel: false,
            submit: true,
            callback: {
                submit: function(element,modal){

                    // Create a spinner animate-rotate
                    var spinner = $(document.createElement('div')).attr({
                        "class": "animate-rotate rounded-circle border border-secondary border-4 d-none",
                        "style": "width: 96px; height: 96px; border-top-color: var(--bs-primary)!important;",
                    }).appendTo(element);

                    // Hide the dialog
                    element.dialog.addClass('opacity-0');

                    // Setup a spinner while waiting for the modal to be submitted
                    setTimeout(() => {

                        // Hide the dialog
                        element.dialog.hide();

                        // Add flex to the modal
                        element.addClass('d-flex align-items-center justify-content-center');

                        // Show the spinner
                        spinner.removeClass('d-none');

                        // AJAX Request
                        $.ajax({
                            url: '/endpoint.php/backups/restore?uuid=' + uuid,
                            type: 'GET',dataType: 'json',
                            success: function(response) {

                                // Hide the modal
                                modal.hide();

                                // Refresh the page
                                window.location.reload();
                            }
                        });
                    }, 300);
                },
            },
        },
        function(modal,component){

            // Save the component
            const componentModal = component;

            // Style the modal
            component.header.addClass('text-bg-info');
            component.footer.submit.addClass('btn-info').removeClass('btn-link').attr({
                "style": "border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;",
            }).text(builder.Locale.get('Restore'));
            component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-arrow-counterclockwise me-1').prependTo(component.footer.submit);

            // Open the modal
            modal.show();
        },
    );
};
const BackupModalUpload = function(dt = null){
    builder.Component(
        "modal",
        {
            onEnter: false,
            destroy: true,
            icon: "upload",
            title: builder.Locale.get("Upload Backup"),
            cancel: false,
            submit: true,
            callback: {
                submit: function(element,modal){
                    element.form.submit();
                },
            },
        },
        function(modal,component){

            // Save the component
            const componentModal = component;

            // Style the modal
            component.header.addClass('text-bg-info');
            component.footer.submit.addClass('btn-info').removeClass('btn-link').attr({
                "style": "border-bottom-right-radius: var(--bs-modal-inner-border-radius) !important;border-bottom-left-radius: var(--bs-modal-inner-border-radius) !important;",
            }).text(builder.Locale.get('Upload'));
            component.footer.submit.icon = $(document.createElement('i')).addClass('bi bi-upload me-1').prependTo(component.footer.submit);

            // Create the form
            component.form = builder.Component(
                'form',
                component.body,
                {
                    class:{
                        form: 'row',
                        field: 'col',
                    },
                    callback:{
                        submit: function(form){

                            // Get the values
                            var values = form.val();

                            // Run the file promise
                            values.file.then(fileData => {

                                // Loop through the files
                                for(const [id, file] of Object.entries(fileData)){

                                    // AJAX Request
                                    $.ajax({
                                        url: '/endpoint.php/backups/upload',
                                        headers: {'X-CSRF-Authorization': CSRF_KEY},
                                        type: 'POST',dataType: 'json',
                                        data: file,
                                        success: function(response) {

                                            // Update CSRF Token
                                            CSRF_KEY = response.CSRF.key;
                                            CSRF_TOKEN = response.CSRF.token;

                                            // Check if the list is an object
                                            if(dt){

                                                // Add the followup to the datatable
                                                dt.row.add(response.record).draw();
                                            }

                                            // Check if a callback is defined
                                            if(typeof callback === "function"){
                                                callback(response.record);
                                            }

                                            // Close the modal
                                            modal.hide();
                                        }
                                    });
                                }
                            }).catch(error => {
                                console.error('Error reading files:', error);
                            });
                        },
                    },
                },
                function(form,component){

                    // csrf
                    form.add(
                        {
                            name: CSRF_KEY,
                            label: 'csrf',
                            icon: 'hash',
                            type: 'hidden',
                            value: CSRF_TOKEN,
                        },
                        function(input,form){
                            input.css('display','none');
                        },
                    );

                    // file
                    form.add(
                        {
                            name: 'file',
                            label: builder.Locale.get('Upload'),
                            icon: 'upload',
                            type: 'file',
                        },
                    );

                    // Open the modal
                    modal.show();
                },
            );
        },
    );
};
