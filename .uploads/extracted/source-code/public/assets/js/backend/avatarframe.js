define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 第一个标签页 - 完全标准
            Table.api.init({
                extend: {
                    index_url: 'avatarframe/index',
                    add_url: 'avatarframe/add',
                    edit_url: 'avatarframe/edit',
                    del_url: 'avatarframe/del',
                    multi_url: 'avatarframe/multi',
                    table: 'avatar_frame',
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: '头像框名称'},
                        {field: 'image', title: '头像框图片', formatter: Table.api.formatter.image},
                        {field: 'status', title: '状态', formatter: Table.api.formatter.status},
                        {field: 'weigh', title: '权重'},
                        {field: 'createtime', title: '创建时间', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            Table.api.bindevent(table);

            // 标签页切换
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                var target = $(e.target).attr("href");
                if (target === "#tab2") {
                    if (!$("#thug-list-content").data('loaded')) {
                        loadThugData();
                    }
                }
            });
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        assignframe: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };

    // 第二个标签页 - 用AJAX加载
    window.loadThugData = function() {
        var $container = $("#thug-list-content");
        $container.data('loaded', false);
        $container.html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i> 加载中...</p>');
        
        $.ajax({
            url: Fast.api.fixurl('avatarframe/thuglist'),
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                $container.data('loaded', true);
                // FastAdmin标准返回格式：{total: xxx, rows: [...]}
                if (res.total !== undefined && res.rows) {
                    renderThugList(res.rows);
                } else if (res.code === 1 && res.data) {
                    // 兼容其他格式
                    renderThugList(res.data.rows || res.data);
                } else {
                    $container.html('<p class="text-danger">加载失败：数据格式错误</p>');
                }
            },
            error: function() {
                $container.html('<p class="text-danger">加载失败，请刷新重试</p>');
            }
        });
    };

    window.assignAvatarFrame = function(thugId) {
        Fast.api.open(Fast.api.fixurl('avatarframe/assignframe?ids=' + thugId), '编辑头像框', {
            end: function() {
                loadThugData();
            }
        });
    };

    function renderThugList(thugList) {
        if (!thugList || thugList.length === 0) {
            $("#thug-list-content").html('<p class="text-center text-muted">暂无数据</p>');
            return;
        }
        
        var html = '<table class="table table-striped table-bordered table-hover table-nowrap">';
        html += '<thead><tr>';
        html += '<th>ID</th>';
        html += '<th>名称</th>';
        html += '<th>头像</th>';
        html += '<th>当前头像框</th>';
        html += '<th>性别</th>';
        html += '<th>状态</th>';
        html += '<th>操作</th>';
        html += '</tr></thead><tbody>';
        
        for (var i = 0; i < thugList.length; i++) {
            var item = thugList[i];
            html += '<tr>';
            html += '<td>' + item.id + '</td>';
            html += '<td>' + (item.name || '') + '</td>';
            html += '<td>' + (item.avatar ? '<img src="' + item.avatar + '" style="max-width:60px;max-height:60px;border-radius:8px;">' : '') + '</td>';
            html += '<td>' + (item.avatar_frame_name ? item.avatar_frame_name : '<span class="label label-default">未设置</span>') + '</td>';
            html += '<td>' + (item.sex == 0 ? '女' : '男') + '</td>';
            var statusLabel = 'label-default';
            var statusText = '未知';
            if (item.sh_status == 1) {
                statusLabel = 'label-success';
                statusText = '启用';
            } else if (item.sh_status == 0) {
                statusLabel = 'label-danger';
                statusText = '禁用';
            } else if (item.sh_status == 2) {
                statusLabel = 'label-warning';
                statusText = '待审核';
            }
            html += '<td><span class="label ' + statusLabel + '">' + statusText + '</span></td>';
            html += '<td><button type="button" class="btn btn-xs btn-success" onclick="assignAvatarFrame(' + item.id + ');"><i class="fa fa-picture-o"></i> 编辑头像框</button></td>';
            html += '</tr>';
        }
        
        html += '</tbody></table>';
        $("#thug-list-content").html(html);
    }

    return Controller;
});
