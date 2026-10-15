define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template', 'echarts', 'echarts-theme'], function ($, undefined, Backend, Table, Form, Template, Echarts) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var myChart = Echarts.init(document.getElementById('echart'), 'walden');

            // 指定图表的配置项和数据
            var option = {
                title: {
                    text: '',
                    subtext: ''
                },
                color: [
                    "#18d1b1",
                    "#3fb1e3",
                    "#626c91",
                    "#a0a7e6",
                    "#c4ebad",
                    "#96dee8"
                ],
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: [__('Register user')]
                },
                toolbox: {
                    show: false,
                    feature: {
                        magicType: {show: true, type: ['stack', 'tiled']},
                        saveAsImage: {show: true}
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: Config.column
                },
                yAxis: {},
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '10',
                    bottom: 30
                }],
                series: [{
                    name: __('Register user'),
                    type: 'line',
                    smooth: true,
                    areaStyle: {
                        normal: {}
                    },
                    lineStyle: {
                        normal: {
                            width: 1.5
                        }
                    },
                    data: Config.userdata
                }]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChart.setOption(option);

            $(window).resize(function () {
                myChart.resize();
            });

            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    myChart.resize();
                }, 0);
            });

            //这句话在多选项卡统计表时必须存在，否则会导致影响的图表宽度不正确
            $(document).on("click", ".charts-custom a[data-toggle=\"tab\"]", function () {
                var that = this;
                setTimeout(function () {
                    var id = $(that).attr("href");
                    var chart = Echarts.getInstanceByDom($(id)[0]);
                    chart.resize();
                }, 0);
            });
            var pieChart = Echarts.init(document.getElementById('pie-chart'), 'walden');
            var option = {
                tooltip: {
                    trigger: 'item',
                    formatter: '{a} <br/>{b}: {c} ({d}%)'
                },
                legend: {
                    orient: 'vertical',
                    left: 10,
                    data: Config.names,
                },
                series: [
                    {
                        name: '商品标题',
                        type: 'pie',
                        radius: ['50%', '70%'],
                        avoidLabelOverlap: false,
                        label: {
                            normal: {
                                show: false,
                                position: 'center'
                            },
                            emphasis: {
                                show: true,
                                textStyle: {
                                    fontSize: '30',
                                    fontWeight: 'bold'
                                }
                            }
                        },
                        labelLine: {
                            normal: {
                                show: false
                            }
                        },
                        data: Config.goodsList,
                    }
                ]
            };
            // 使用刚指定的配置项和数据显示图表。
            pieChart.setOption(option);

            var orderChart = Echarts.init($('#order-chart')[0], 'walden');
            orderChart.setOption({
                legend: {data: ['商品订单']},
                tooltip: {trigger: 'axis',formatter: "{b}<br>{a0} : {c0} 个 <br>"},
                toolbox: {show: true,feature: {magicType: {show: true,type: ['line','bar']},restore: {show: true},saveAsImage: {show: true}}},
                calculable: true,
                xAxis: [
                    {
                        type: 'category',
                        data: Config.weekDays
                    }
                ],
                yAxis: [
                    {type: 'value'}
                ],
                yAxis: [
                    {type: 'value'}
                ],
                series: [
                    {
                        name: '商品订单',
                        type: 'line',
                        data: Config.orderNumber,
                        markPoint: {data: [{type: 'max',name: '最大值'},{type: 'min',name: '最小值'}]}
                    },
                ]
            });
        }

    };

    return Controller;
});
