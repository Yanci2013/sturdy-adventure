define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
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
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'parent_nickname', title: '上级推荐人', operate: false, formatter: function(value, row, index) {
                            return value || '无';
                        }},
                        {field: 'promote_parent_nickname', title: '推广上级', operate: false, formatter: function(value, row, index) {
                            return value || '无';
                        }},
                        {field: 'money', title: __('Money'), operate: 'BETWEEN', sortable: true, formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'quota', title: '商家额度', operate: 'BETWEEN', sortable: true, formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'role_type', title: '角色类型', searchList: {weironzheng: '未认证', dashou: '打手', jindashou: '金牌打手', guanshi: '管事', zuzhang: '组长', zongguan: '总管', jinshangjia: '金牌商家', shangjia: '商家', kefu: '客服', shouhou: '售后'}, formatter: function(value, row, index) {
                            var roleMap = {
                                weironzheng: '未认证',
                                dashou: '打手',
                                jindashou: '金牌打手',
                                guanshi: '管事',
                                zuzhang: '组长',
                                zongguan: '总管',
                                jinshangjia: '金牌商家',
                                shangjia: '商家',
                                kefu: '客服',
                                shouhou: '售后'
                            };
                            return roleMap[value] || value;
                        }},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index) {
                            var that = this;
                            return Table.api.formatter.operate.call(this, value, row, index) + 
                                '<a href="javascript:;" class="btn btn-xs btn-success btn-dialog" data-url="user/user/member?ids=' + row.id + '" data-title="会员设置"><i class="fa fa-vip-card"></i> 会员设置</a>';
                        }}
                    ]
                ]
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
        member: function () {
            Controller.api.bindevent($("form[role=form]"), function(data, ret) {
                // 成功后刷新表格
                var table = $("#table");
                if (table.length) {
                    table.bootstrapTable('refresh');
                }
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});