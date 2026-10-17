define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    //del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'score', title: __('Score'), operate: false, sortable: true},
                        {field: 'money', title: __('佣金'), operate: false, sortable: true},
                        {field: 'balance', title: '余额', operate: false, sortable: true, formatter: function(value) {
                            return '<span style="color:#ff6600;font-weight:bold;">' + (parseFloat(value) || 0).toFixed(2) + '</span>';
                        }},
                        {field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'loginip', title: __('Loginip'), formatter: Table.api.formatter.search},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'ban_status', title: '封禁状态', searchList: {"0":"正常","1":"已封禁"}, formatter: function(value) {
                            if (value == 1) return '<span class="label label-danger">已封禁</span>';
                            return '<span class="label label-success">正常</span>';
                        }},
                        {field: 'share', title: __('分销权限'), searchList: {"0":__('否'),"1":__('是')}, formatter: Table.api.formatter.toggle},
                        {field: 'group_chat', title: __('群聊权限'), searchList: {"0":__('否'),"1":__('是')}, formatter: Table.api.formatter.toggle},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            formatter: function (value, row, index) {
                                var html = Table.api.formatter.operate.call(this, value, row, index);
                                html += '<div class="btn-group btn-group-xs" style="margin-left:5px;">';
                                html += '<a href="javascript:;" class="btn btn-success btn-balance-op" data-id="' + row.id + '" data-action="add" title="添加余额"><i class="fa fa-plus"></i></a>';
                                html += '<a href="javascript:;" class="btn btn-warning btn-balance-op" data-id="' + row.id + '" data-action="set" title="修改余额"><i class="fa fa-pencil"></i></a>';
                                html += '<a href="javascript:;" class="btn btn-danger btn-balance-op" data-id="' + row.id + '" data-action="sub" title="扣减余额"><i class="fa fa-minus"></i></a>';
                                html += '</div>';
                                html += '<div class="btn-group btn-group-xs" style="margin-left:5px;">';
                                if (row.ban_status == 1) {
                                    html += '<a href="javascript:;" class="btn btn-success btn-unban" data-id="' + row.id + '" title="解封"><i class="fa fa-unlock"></i> 解封</a>';
                                } else {
                                    html += '<a href="javascript:;" class="btn btn-danger btn-ban" data-id="' + row.id + '" title="封禁"><i class="fa fa-ban"></i> 封禁</a>';
                                }
                                html += '</div>';
                                return html;
                            }
                        }
                    ]
                ]
            });

            // 余额操作按钮事件
            $(document).on('click', '.btn-balance-op', function () {
                var user_id = $(this).data('id');
                var action = $(this).data('action');
                var titles = {add: '添加余额', sub: '扣减余额', set: '修改余额'};
                Fast.api.open('user/balance/change?user_id=' + user_id + '&action=' + action, titles[action], {
                    callback: function () {
                        table.bootstrapTable('refresh');
                    }
                });
            });

            // 封禁按钮事件
            $(document).on('click', '.btn-ban', function () {
                var user_id = $(this).data('id');
                Fast.api.open('user/user/ban?ids=' + user_id, '封禁用户', {
                    callback: function () {
                        table.bootstrapTable('refresh');
                    }
                });
            });

            // 解封按钮事件
            $(document).on('click', '.btn-unban', function () {
                var user_id = $(this).data('id');
                Layer.confirm('确定要解封该用户吗？', {icon: 3, title: '提示'}, function (index) {
                    $.post('user/user/unban?ids=' + user_id, function (data) {
                        Layer.close(index);
                        if (data.code === 1) {
                            Toastr.success(data.msg);
                            table.bootstrapTable('refresh');
                        } else {
                            Toastr.error(data.msg);
                        }
                    }, 'json');
                });
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        ban: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});