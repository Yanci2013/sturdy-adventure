define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'goods/goods/index' + location.search,
                    add_url: 'goods/goods/add',
                    edit_url: 'goods/goods/edit',
                    del_url: 'goods/goods/del',
                    multi_url: 'goods/goods/multi',
                    import_url: 'goods/goods/import',
                    table: 'goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'sort',
                sortOrder:'desc',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {
                            field: 'logo',
                            title: __('Logo'),
                            operate: false,
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.images
                        },
                        //{field: 'images', title: __('Images'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'one_categorys', title: __('One_category'), operate: false},
                        {field: 'two_categorys', title: __('Two_category'), operate: false},
                        {
                            field: 'one_category',
                            title: __('One_category'),
                            visible: false,
                            searchList: $.getJSON("goods/goods/onetype"),
                            formatter: Table.api.formatter.status,
                            operate: 'LIKE'
                        },
                        {
                            field: 'two_category',
                            title: __('Two_category'),
                            visible: false,
                            searchList: $.getJSON("goods/goods/twotype"),
                            formatter: Table.api.formatter.status,
                            operate: 'LIKE'
                        },
                        //{field: 'synopsis', title: __('Synopsis'), operate: 'LIKE'},
                        {field: 'price', title: __('Price'), operate: false},
                        {field: 'line_price', title: __('Line_price'), operate: false},
                        {field: 'received_price', title: __('Received_price'), operate: false},
                        {field: 'com_price', title: __('Com_price'), operate: false},
                        {field: 'com_line_price', title: __('Com_line_price'), operate: false},
                        {field: 'com_received_price', title: __('Com_received_price'), operate: false},
                        {field: 'number', title: __('Number'), operate: false},
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {"0": __('Status 0'), "1": __('Status 1')},
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'award',
                            title: __('是否参加抽奖'),
                            searchList: {"0": __('否'), "1": __('是')},
                            formatter: Table.api.formatter.toggle
                        },
                        {field: 'sort', title: __('Sort'), operate: false},
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
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
