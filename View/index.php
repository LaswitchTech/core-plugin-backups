<article id="layout"></article>
<script>
    $(document).ready(function(){
        builder.Layout('index',"#layout",{
            endpoint: '/backups/fetchAll',
            conditions: null,
            primary: 'name',
            actions: {
                download:{
                    label: builder.Locale.get('Download'),
                    class:{
                        button: 'text-bg-light',
                    },
                    icon:'download',
                    action:function(event, table, dt, node, row, data){
                        window.location.href = '/plugin/backups/download?name='+data.name;
                    }
                },
                restore:{
                    label: builder.Locale.get('Restore'),
                    class:{
                        button: 'text-bg-info',
                    },
                    icon:'arrow-counterclockwise',
                    action:function(event, table, dt, node, row, data){
                        BackupModalRestore(data.name);
                    }
                },
                delete:{
                    label: builder.Locale.get('Delete'),
                    class:{
                        button: 'text-bg-danger',
                    },
                    icon:'trash',
                    action:function(event, table, dt, node, row, data){
                        var uuids = [];
                        uuids.push(data);
                        BackupModalDelete(uuids, dt);
                    }
                },
            },
            buttons: [
                {
                    className : 'btn-success',
                    init: function (dt, node){
                        $(node).removeClass('btn-secondary');
                    },
                    text: '<i class="bi bi-plus-lg"></i>',
                    action:function(e, dt, node, config){
                        BackupModalCreate(dt);
                    },
                },
                {
                    className : 'btn-light',
                    init: function (dt, node){
                        $(node).removeClass('btn-secondary');
                    },
                    text: '<i class="bi bi-upload"></i>',
                    action:function(e, dt, node, config){
                        BackupModalUpload(dt);
                    },
                },
                {
                    extend : 'selected',
                    className : 'btn-danger requires-selection d-none',
                    init: function (dt, node){
                        $(node).removeClass('btn-secondary');
                    },
                    text: '<i class="bi bi-trash"></i>',
                    action:function(e, dt, node, config){
                        var uuids = dt.rows({ selected: true }).data().toArray();
                        BackupModalDelete(uuids, dt);
                    },
                },
            ],
            columns: [
                {
                    targets: 0,
                    visible: true,
                    className: 'all',
                    responsivePriority: 1,
                    title: builder.Locale.get('Name'),
                    name: 'name',
                    data: 'name',
                    defaultContent: '',
                },
                {
                    targets: 1,
                    visible: false,
                    className: 'min-md',
                    responsivePriority: 100,
                    title: builder.Locale.get('Path'),
                    name: 'path',
                    data: 'path',
                    defaultContent: '',
                },
                {
                    targets: 2,
                    visible: true,
                    className: 'min-md',
                    responsivePriority: 10,
                    title: builder.Locale.get('Size'),
                    name: 'size',
                    data: 'size',
                    defaultContent: '',
                },
                {
                    targets: 3,
                    visible: true,
                    className: 'min-md',
                    responsivePriority: 20,
                    title: builder.Locale.get('Date'),
                    name: 'date',
                    data: 'date',
                    defaultContent: '',
                },
                {
                    targets: 4,
                    visible: false,
                    className: 'min-md',
                    responsivePriority: 200,
                    title: builder.Locale.get('Hash'),
                    name: 'hash',
                    data: 'hash',
                    defaultContent: '',
                },
            ],
        });
    });
</script>
