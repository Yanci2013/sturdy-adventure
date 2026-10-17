define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'goods/comment/index' + location.search,
                    del_url: 'goods/comment/del',
                    multi_url: 'goods/comment/multi',
                    table: 'goods_comment',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'goods_title', title: '商品名称', operate: 'LIKE'},
                        {field: 'nickname', title: '用户昵称', operate: false},
                        {field: 'rating', title: '评分', operate: false},
                        {field: 'content', title: '评论内容', operate: false, formatter: function(value) {
                            if (!value) return '-';
                            return value.length > 30 ? value.substring(0, 30) + '...' : value;
                        }},
                        {field: 'images', title: '图片', operate: false, events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'status', title: '状态', searchList: {"0": "隐藏", "1": "显示"}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: '评论时间', operate: false},
                        {field: 'operate', title: '操作', table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
