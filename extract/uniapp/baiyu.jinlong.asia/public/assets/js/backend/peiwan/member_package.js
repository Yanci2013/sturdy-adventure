define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/member_package/index' + location.search,
                    add_url: 'peiwan/member_package/add',
                    edit_url: 'peiwan/member_package/edit',
                    del_url: 'peiwan/member_package/del',
                    multi_url: 'peiwan/member_package/multi',
                    import_url: 'peiwan/member_package/import',
                    table: 'member_package',
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
                        {field: 'game.name', title: '游戏名称', operate: 'LIKE'},
                        {field: 'image', title: '套餐图片', events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'trial_price', title: '体验价（元）', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'trial_days', title: '体验天数'},
                        {field: 'formal_price', title: '正式价格（元）', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'valid_days', title: '有效天数'},
                        {field: 'trial_commission_guanshi', title: '体验-管事', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'trial_commission_zuzhang', title: '体验-组长', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'trial_commission_zongguan', title: '体验-总管', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'first_commission_guanshi', title: '首次-管事', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'first_commission_zuzhang', title: '首次-组长', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'first_commission_zongguan', title: '首次-总管', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'renew_commission_guanshi', title: '续费-管事', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'renew_commission_zuzhang', title: '续费-组长', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'renew_commission_zongguan', title: '续费-总管', operate:'BETWEEN', formatter: function(value, row, index) {
                            return '¥' + (parseFloat(value) || 0).toFixed(2);
                        }},
                        {field: 'status', title: __('Status'), searchList: {"normal":__('Status normal'),"hidden":__('Status hidden')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
