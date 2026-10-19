define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/community_post/index' + location.search,
                    add_url: 'peiwan/community_post/add',
                    edit_url: 'peiwan/community_post/edit',
                    del_url: 'peiwan/community_post/del',
                    multi_url: 'peiwan/community_post/multi',
                    import_url: 'peiwan/community_post/import',
                    table: 'community_post',
                }
            });

            var table = $("#table");

            // 用户信息格式化器
            var userFormatter = function(value, row) {
                var user = row.user;
                if (!user) return '-';
                var html = '<div style="display:flex;align-items:center;">';
                html += '<img src="' + (user.avatar || '/assets/img/avatar.png') + '" style="width:30px;height:30px;border-radius:50%;margin-right:5px;">';
                html += '<span>' + (user.nickname || '-') + '</span>';
                html += '<span style="color:#999;margin-left:5px;">(ID:' + user.id + ')</span>';
                html += '</div>';
                return html;
            };

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
                        {field: 'user_id', title: '用户', operate: false, formatter: userFormatter},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'is_top', title: __('Is_top'), searchList: {"0":__('Is_top 0'),"1":__('Is_top 1')}, formatter: function(value, row, index) {
                            return value == 1 ? '<span class="label label-danger">置顶</span>' : '<span class="label label-default">普通</span>';
                        }},
                        {field: 'view_count', title: __('View_count')},
                        {field: 'like_count', title: __('Like_count')},
                        {field: 'comment_count', title: __('Comment_count')},
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
        },
        edit: function () {
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
