define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thuggift/index' + location.search,
                    add_url: 'thuggift/add',
                    edit_url: 'thuggift/edit',
                    del_url: 'thuggift/del',
                    table: 'thug_gift',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'thug_name', title: __('打手名称'), operate: 'LIKE', formatter: function(value, row, index) {
                            if (row.thug_name === '全局默认') {
                                return '<span class="label label-warning">全局默认配置</span>';
                            }
                            return value;
                        }},
                        {field: 'thug_avatar', title: __('打手头像'), operate: 'LIKE', events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'gift_ids', title: __('礼物ID列表'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('禁用'),"1":__('启用')}, formatter: Table.api.formatter.toggle},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 全局配置按钮
            $('.btn-global').click(function () {
                Fast.api.open('thuggift/global', '全局配置');
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        global: function () {
            Controller.api.bindevent();
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        records: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thuggift/records' + location.search,
                    table: 'gift_record',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'createtime',
                sortOrder: 'desc',
                columns: [
                    [
                        {field: 'id', title: __('Id')},
                        {field: 'user_name', title: __('用户名称'), operate: 'LIKE'},
                        {field: 'user_avatar', title: __('用户头像'), operate: 'LIKE', events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'thug_name', title: __('打手名称'), operate: 'LIKE'},
                        {field: 'gift_name', title: __('礼物名称'), operate: 'LIKE'},
                        {field: 'gift_icon', title: __('礼物图标'), operate: 'LIKE', events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'price', title: __('单价')},
                        {field: 'num', title: __('数量')},
                        {field: 'total_price', title: __('总价')},
                        {field: 'createtime', title: __('赠送时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        stats: function () {
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
