define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/client_goods/index' + location.search,
                    add_url: 'peiwan/client_goods/add',
                    edit_url: 'peiwan/client_goods/edit',
                    del_url: 'peiwan/client_goods/del',
                    multi_url: 'peiwan/client_goods/multi',
                    import_url: 'peiwan/client_goods/import',
                    table: 'client_goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: '商品标题', operate: 'LIKE'},
                        {field: 'game.name', title: '游戏', operate: 'LIKE'},
                        {field: 'server.server_name', title: '区服', operate: 'LIKE'},
                        {field: 'image', title: '商品图片', operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'price', title: '价格（用户付款）'},
                        {field: 'grab_price', title: '抢单价格（打手看到）'},
                        {field: 'status', title: __('Status'), searchList: {"normal":__('Status normal'),"hidden":__('Status hidden')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            Controller.api.bindGameServerEvent();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.bindGameServerEvent();
            // 编辑页面初始化加载区服
            Controller.api.initServerSelect();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            // 游戏-区服联动（使用普通select + AJAX）
            bindGameServerEvent: function() {
                var serverSelect = $('#c-server_id');
                
                // 监听游戏选择变化（selectpage的隐藏input）
                $(document).on('change', '#c-game_id', function() {
                    var gameId = $(this).val();
                    Controller.api.loadServerList(gameId);
                });
            },
            // 加载区服列表
            loadServerList: function(gameId, selectedValue) {
                var serverSelect = $('#c-server_id');
                
                // 清空区服选项
                serverSelect.html('<option value="">加载中...</option>');
                
                if (!gameId) {
                    serverSelect.html('<option value="">请先选择游戏</option>');
                    return;
                }
                
                // AJAX请求获取该游戏的区服列表
                $.ajax({
                    url: 'peiwan/game_server/index',
                    type: 'POST',
                    data: {
                        keyField: 'id',
                        'custom[game_id]': gameId,
                        pageSize: 100
                    },
                    dataType: 'json',
                    success: function(res) {
                        var html = '<option value="">请选择区服</option>';
                        if (res.list && res.list.length > 0) {
                            $.each(res.list, function(i, item) {
                                var selected = (selectedValue && item.id == selectedValue) ? ' selected' : '';
                                html += '<option value="' + item.id + '"' + selected + '>' + item.server_name + '</option>';
                            });
                        } else {
                            html = '<option value="">该游戏暂无区服</option>';
                        }
                        serverSelect.html(html);
                    },
                    error: function() {
                        serverSelect.html('<option value="">加载失败</option>');
                    }
                });
            },
            // 编辑页面初始化区服select
            initServerSelect: function() {
                var serverSelect = $('#c-server_id');
                var initValue = serverSelect.data('init-value');
                var initGame = serverSelect.data('init-game');
                
                if (initGame) {
                    Controller.api.loadServerList(initGame, initValue);
                }
            }
        }
    };
    return Controller;
});
