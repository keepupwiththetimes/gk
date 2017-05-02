/**
 * 移动端弹出层，cloudLayer.js - V1.0
 * by itshajia
 * 个人博客：http://www.uioweb.com
 * Email：<itshajia@gmail.com>
 * */
jQuery( function( $ ) {
    // 数组删除执行值
    Array.prototype.indexOf = function(val) {
        for (var i = 0; i < this.length; i++) {
            if (this[i] == val) return i;
        }
        return -1;
    };
    Array.prototype.remove = function(val) {
        var num = 0, index;
        for ( var i = 0; i< this.length; i++ ) {
            if ( this[i] == val ) num++;
        }

        for ( var i = 0; i< num; i++ ) {
            index = this.indexOf(val);
            if ( index > -1 ) {
                this.splice(index, 1);
            }
        }
    };

    $.cloudLayer = {
        index: 1, zIndex: 999999, prefix: 'cloudLayer_', blurTarget: 'container',
        screenWidth: window.screen.width, screenHeight: window.screen.height,
        winScrollTop: 0
    };

    var CL = $.cl = $.cloudLayer;

    // 工具类方法
    $.extend( CL, {
        Data: {
            layer: {},
            form: [],
            alert: [],
            confirm: [],
            tip: [],
            blur: [ CL.blurTarget ]
        },
        Tool: {
            clearForeLayer: function( type ) {
                for ( var i = 0; i < CL.Data[ type ].length; i++ ) {
                    if( CL.Data['layer'][ CL.Data[ type ][i] ] && CL.Data['layer'][ CL.Data[ type ][i] ].close ) {
                        CL.Data['layer'][ CL.Data[ type ][i] ].close();
                    }
                    CL.Tool.removeLayer( CL.Data[ type ][i], type );
                }
            },
            addLayer: function( id, type ) {
                if ( !id ) return;
                if ( CL.Data[ type ] && CL.Data[ type ].indexOf( id )==-1 ) {
                    CL.Data[ type ].push( id );
                }
            },
            removeLayer: function( id, type ) {
                CL.Data[ type ].remove( id );
            },
            setBone: function( L ) {
                var Options = L.OPTIONS, style;
                if ( Options.layerHeight && 1==2 ) {
                    style = 'style="height: '+ CL.screenHeight +'px;"';
                } else {
                    style = '';
                }
                var Htm = '<div class="cloudLayer '+ Options['globalClass'] +'" '+ style +'>' +
                    getLayerBgHtm()+
                    getLayerLoadingHtm()+
                    '<div class="cloudLayerOutter">' +

                    '<div class="cloudLayerInner">' +
                    '<div class="cloudLayerBox">' +
                    getBoxBgHtm()+
                    '<div class="cloudLayerBoxInner">' +
                    '</div>'+
                    '</div>'+
                    '</div>'+
                    '</div>'+
                    '</div>';

                // 加载层
                function getLayerLoadingHtm() {
                    var Htm;

                    if ( Options.loading ) {
                        Htm = '<div class="cloudLayerLoading">' +
                            '<div class="cloudLayerLoadingPanel">' +
                            '<div class="cloudLayerLoadingPannelInner">' +
                            '<div class="cloudLayerLoadingFg"></div>'+
                            '</div>'+
                            '</div>'+
                            '</div>';
                    } else {
                        Htm = '';
                    }
                    return Htm;
                }

                // 获取背景层Htm
                function getLayerBgHtm() {
                    var Htm;

                    if ( Options.layerBg ) {
                        Htm = '<div id="cloudLayerBg_'+ Options.zIndex +'" class="cloudLayerBg " style="background-color:'+ Options.layerBgColor +';"></div>';
                        //Htm = '<canvas id="cloudLayerBg_'+ Options.zIndex +'" class="cloudLayerBg '+ blurClass +'"></canvas>';
                    } else {
                        Htm = '';
                    }
                    return Htm;
                }

                // 获取对象背景层Htm
                function getBoxBgHtm() {
                    var Htm, blurClass;

                    if ( Options.boxBg ) {
                        if ( Options.boxBgBlur ) {
                            blurClass = ' cloudLayerBlur ';
                        } else {
                            blurClass = '';
                        }
                        Htm = '<div id="cloudLayerBoxBg_'+ Options.zIndex +'" class="cloudLayerBoxBg" style="background-color:'+ Options.boxBgColor +';"></div>';
                        //Htm = '<canvas id="cloudLayerBoxBg_'+ Options.zIndex +'" class="cloudLayerBoxBg '+ blurClass +'"></canvas>';
                    } else {
                        Htm = '';
                    }
                    return Htm;
                }

                return $(Htm);
            }
        }
    });


    // 基础属性配置
    var BaseSettings = {
        title          : '操作提示',
        msg            : '',
        zIndex         : CL.zIndex,
        tmplId         : '',
        Dom            : '',
        tmplData       : {},
        hide           : false,
        loading        : true,
        layerBg        : true,
        layerBgBlur    : true,
        layerBgColor   : 'rgba(0, 0, 0, 0.35)',
        boxBg          : false,
        boxBgBlur      : false,
        boxBgColor     : 'rgba(255, 255, 255, 0.80)',
        effectTime     : 250,
        effectClass    : {},
        delayTime      : 500,
        layerHeight    : true,
        unique         : false,
        insertBeforeFun: function( L ) {},
        insertAfterFun : function( L ) {},
        OKFun          : function( L ) {},
        CancelFun      : function( L ) {}
    };

    // 特效注册
    $.Velocity
        .RegisterEffect("transition.flipXIn", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 1, rotateY: [ 0, -55 ] } ]
            ]
        })
        .RegisterEffect("transition.flipXOut", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 0, rotateY: 55 } ]
            ],
            reset: { rotateY: 0 }
        })
        .RegisterEffect("transition.bounceInRight", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                //[ { opacity: 0, translateX: '100%' }, 0 ],
                [ { opacity: 0.5, translateX: '-30px' } ],
                [ { opacity: 1, translateX: '10px' } ],
                [ { opacity: 1, translateX: '0' } ]
            ]
        })
        .RegisterEffect("transition.bounceOutRight", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 1, translateX: '0px' } ],
                [ { opacity: 0, translateX: '100%' } ]
            ]
        })
        .RegisterEffect("transition.bounceIn", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 0, scale: '0.3' } ],
                [ { opacity: 1, scale: '1.05' } ],
                [ { opacity: 1, scale: '0.9' } ],
                [ { opacity: 1, scale: '1' } ]
            ]
        })
        .RegisterEffect("transition.fadeOut", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 1} ],
                [ { opacity: 0} ]
            ]
        })
        .RegisterEffect("transition.bounceInDown", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 0, translateY: '-100%'} ],
                [ { opacity: 1, translateY: '0px'} ]
            ]
        })
        .RegisterEffect("transition.bounceOutUp", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 1, translateY: '0'} ],
                [ { opacity: 0, translateY: '-100%'} ]
            ]
        })
        .RegisterEffect("transition.bounceInUp", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 0, translateY: '100%'} ],
                [ { opacity: 0.5, translateY: '-30px'} ],
                [ { opacity: 1, translateY: '-10px'} ],
                [ { opacity: 1, translateY: '0'} ]
            ]
        })
        .RegisterEffect("transition.bounceOutDown", {
            defaultDuration: BaseSettings.effectTime,
            calls: [
                [ { opacity: 1, translateY: '0'} ],
                [ { opacity: 0.5, translateY: '-20px'} ],
                [ { opacity: 0, translateY: '-100%'} ]
            ]
        });



    var BaseLayer = function( settings ){
        var _this = this;
        var Default = $.extend({}, BaseSettings);
        settings = $.extend( Default, settings );

        // 初始化
        _this.initialize = function() {
            _this.OPTIONS = settings;
            if ( _this.OPTIONS['unique'] ) {
                CL.Tool.clearForeLayer( _this.OPTIONS['type'] );
            }
            _this.initBone();
            CL.Tool.addLayer( _this.CL.attr('id'), _this.OPTIONS['type'] );
            CL.Data.layer[ _this.CL.attr('id') ] = _this;
        }

        // 建立“层基本框架“
        _this.initBone = function() {
            var layer, layerBox;

            layer = CL.Tool.setBone( _this );
            if ( settings.no_padding ) layer.addClass('no_padding');
            _this.CL = layer;
            _this.CLOutter = $('.cloudLayerOutter', layer);
            _this.CLBg = $('.cloudLayerBg', layer);
            _this.CLBoxInner = $('.cloudLayerBoxInner', layer);
            _this.CLOK       = $('.cloudLayerOK', layer);
            _this.CLCancel    = $('.cloudLayerCancel', layer);

            layer.attr('id', CL.prefix + CL.index).css({
                'z-index': parseInt( settings.zIndex) + parseInt( CL.index )
            });

            if ( settings.insertBeforeFun && settings.insertBeforeFun instanceof Function ) {
                settings.insertBeforeFun( _this );
            }

            _this.initDom();
            _this.insertTmplDom();
            _this.CLBodyInner = $('.cloudLayerBodyInner', layer);
            $('body').append( layer );


            if ( settings.insertAfterFun && settings.insertAfterFun instanceof Function ) {
                settings.insertAfterFun( _this );
            }

            _this.setValues();
            $(window).bind('resize', function( ){ _this.setValues(); });
            _this.onEvents();
            _this.show();

            _this.autoClear();
            CL.index++;
        }

        // 构造"内层结构"
        _this.initDom = function() {
            _this.setHead();
            _this.setBody();
            _this.setFoot();
        }

        // "模糊效果"
        _this.setBlur = function() {
            if ( settings.layerBgBlur ) {
                _this.target = $('#'+ CL.Data.blur[ CL.Data.blur.length - 1]);
                if ( _this.target && _this.target.get(0) ) {
                    if ( CL.Data.blur.length==1 ) {
                        _this.target.attr('winScrollTop', $(window).scrollTop());
                    } else {
                        _this.target.attr('windowScrollTop', $('.cloudLayerContainer', _this.target).scrollTop());
                    }

                    _this.target.addClass( 'cloudLayerBlur' );
                    CL.Tool.addLayer( _this.CL.attr('id'), 'blur' );
                }

            }
        }

        // "清除模糊效果"
        _this.clearBlur = function() {
            if ( settings.layerBgBlur && _this.target && _this.target.get(0) ) {
                CL.Tool.removeLayer( _this.CL.attr('id'), 'blur' );
                if ( CL.Data.blur.length ) {
                    _this.target.removeClass('cloudLayerBlur');

                    if ( CL.Data.blur.length==1 ) {
                        $(window).scrollTop( _this.target.attr('winScrollTop') );
                    } else {
                        $('.cloudLayerContainer', _this.target).scrollTop( _this.target.attr('winScrollTop') );
                    }
                }
            }
        }

        // 头部信息
        _this.setHead = function() {
            var OKHtm, CancelHtm;

            if ( settings.OKButton['show'] ) {
                OKHtm = '<a href="javascript:;" class="cloudLayerOK">'+ settings.OKButton['name'] +'</a>';
            } else {
                OKHtm = '';
            }
            if ( settings.CancelButton['show'] ) {
                CancelHtm = '<a href="javascript:;" class="cloudLayerCancel">'+ settings.CancelButton['name'] +'</a>';
            } else {
                CancelHtm = '';
            }
            var Htm = '<div class="cloudLayerHead">' +
                CancelHtm+
                '<span class="cloudLayerTitle">'+ settings.title +'</span>'+
                OKHtm+
                '</div>';

            _this.CLBoxInner.append( Htm );
        }


        _this.setBody = function() {
            var Htm;

            Htm = '<div class="cloudLayerBody">' +
                '<div class="cloudLayerBodyInner">' +
                '<div class="cloudLayerContainer"></div>'+
                '</div>'+
                '</div>';

            _this.CLBoxInner.append( Htm );
        }

        _this.insertTmplDom = function() {
            if ( $.tmpl ) {
                $('.cloudLayerContainer', _this.CL).height('auto').append(
                    $('#'+ settings.tmplId).tmpl( settings.tmplData )
                );
            }

            if ( settings.tmplDom ) {
                $('.cloudLayerContainer', _this.CL).height('auto').append(
                    settings.tmplDom
                );
            }
            _this.CL.addClass('complete');
        }

        _this.setFoot = function() {}

        _this.setValues = function() {
            var hH, LH;

            LH = _this.CL.height();
            $('.cloudLayerBg,.cloudLayerOutter', _this.CL).height( LH );
            hH = $('.cloudLayerHead', _this.CL).height();
            _this.CLBodyInner.height( LH - hH );
            $('.cloudLayerContainer', _this.CL).css({paddingBottom: '50px'});
        }

        _this.show = function() {
            _this.CL.show();
            setTimeout( function() {
                _this.setBlur();
                _this.CLOutter.velocity( settings.effectClass.In );
            }, 150);
        }

        _this.close = function( immediately ) {
            if ( immediately ) {
                _this.CL.hide();
            }
            _this.CLOutter.velocity( settings.effectClass.Out );
            _this.CL.delay( settings.delayTime , 'form')
                .queue('form', function( next ){
                    if ( !settings.hide ) {
                        _this.CL.remove();
                    } else{
                        _this.CL.hide();
                    }
                    _this.clearBlur();
                    next();
                })
                .dequeue('form');
        }

        _this.onEvents = function() {
            _this.CL.unbind().bind('click', function(e){
                e.stopPropagation();
                e.preventDefault();
            }).bind('touchmove', function( e ){
                e.stopPropagation();
            });
            _this.onClose();
            _this.onOK();
        }

        _this.onClose = function() {
            $('.cloudLayerCancel', _this.CL).unbind('click').bind('click', function(e){
                e.stopPropagation();
                e.preventDefault();
                _this.doClose();
            });
        }

        _this.doClose = function() {
            if ( settings.CloseFun && settings.CloseFun instanceof Function ) {
                settings.CloseFun( this );
            }

            _this.close();
        }

        _this.onOK = function() {
            $('.cloudLayerOK', _this.CL).unbind('click').bind('click', function(e){
                e.stopPropagation();
                e.preventDefault();
                _this.doOK();
            });
        }

        _this.doOK = function() {
            if ( settings.OKFun && settings.OKFun instanceof Function ) {
                if ( settings.OKFun( this ) ) {
                    _this.close();
                }
            }
        }

        _this.autoClear = function( ){

        }
    }


    /**
     * 表单弹出层
     * */
    CL.form = function( options ) {
        var settings = {
            type        : 'form',
            no_padding  : false,
            globalClass : CL.prefix +'form',
            layerBgColor: '#ffffff',
            effectClass : {In: 'transition.bounceInRight', Out: 'transition.bounceOutRight'},
            OKButton    : { name: '保存', show: true },
            CancelButton: { name: '取消', show: true}
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        L.initialize();
        return L;
    };


    /**
     * 信息弹出层
     * */
    CL.alert = function( msg, success, callback ) {
        if ( !msg ) {
            if ( callback && callback instanceof Function ) callback();
            return ;
        }

        var settings = {
            type       : 'alert',
            no_padding : false,
            globalClass: CL.prefix +'alert',
            layerBgBlur: false,
            boxBg      : true,
            boxBgBlur  : true,
            boxBgColor : 'rgba(255, 255, 255, 0.95)',
            effectClass: {In: 'transition.bounceIn', Out: 'transition.fadeOut'},
            OKButton   : { name: '好', show: true }
        };

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {},
            setBody: function() {
                var Htm;

                Htm = '<div class="cloudLayerBody">' +
                    '<div class="cloudLayerBodyInner">' +
                    '<div class="cloudLayerContainer">'+ msg +'</div>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setFoot: function() {
                var Htm;

                Htm = '<div class="cloudLayerFoot">' +
                    '<div class="cloudLayerFootInner">' +
                    '<a href="javascript:;" class="cloudLayerOK">好</a>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setValues: function() {
                L.CLOutter.height( L.CLBoxInner.height() );
            },
            doOK: function() {
                L.close();
            },
            close: function( immediately ) {
                if ( immediately ){
                    L.CL.hide();
                }

                L.CLOutter.velocity( settings.effectClass.Out );
                L.clearBlur();
                L.CL.delay(  settings.delayTime, 'alert')
                    .queue('alert', function( next ){
                        L.CL.remove();

                        if ( callback && callback instanceof Function ) callback();
                        next();
                    })
                    .dequeue('alert');

            },
            autoClear: function() {
                setTimeout( function(){
                    //L.close();
                } ,5000);
            }
        });
        L.initialize();
        return L;
    }


    /**
     * 确认信息弹出层
     * */
    CL.confirm = function( options ) {
        var settings = {
            title       : '确认提示',
            msg         : '真的要执行该操作？',
            type        : 'confirm',
            no_padding  : false,
            globalClass : CL.prefix +'confirm',
            layerBgBlur : false,
            boxBg       : true,
            boxBgBlur   : true,
            boxBgColor  : 'rgba(255, 255, 255, 0.95)',
            effectClass : {In: 'transition.bounceIn', Out: 'transition.fadeOut'},
            OKButton    : { name: '好', show: true },
            CancelButton: {name: '取消', show: true}
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {
                var Htm;

                if ( settings.title ) {
                    Htm = '<div class="cloudLayerHead">'+ settings.title +'</div>';
                    L.CL.addClass('title');
                } else {
                    Htm = '';
                }
                L.CLBoxInner.append( Htm );
            },
            setBody: function() {
                var Htm;

                Htm = '<div class="cloudLayerBody">' +
                    '<div class="cloudLayerBodyInner">' +
                    '<div class="cloudLayerContainer">'+ settings.msg +'</div>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setFoot: function() {
                var Htm;

                Htm = '<div class="cloudLayerFoot">' +
                    '<div class="cloudLayerFootInner">' +
                    '<a href="javascript:;" class="cloudLayerCancel">取消</a>'+
                    '<a href="javascript:;" class="cloudLayerOK">好</a>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setValues: function() {
                L.CLOutter.height( L.CLBoxInner.height() );
            }
        });
        L.initialize();
        return L;
    }

    /**
     * 确认信息弹出层
     * */
    CL.confirm1 = function( options ) {
        var settings = {
            title       : '温馨提示',
            msg         : '不存在此地址',
            type        : 'confirm',
            no_padding  : false,
            globalClass : CL.prefix +'confirm',
            layerBgBlur : false,
            boxBg       : true,
            boxBgBlur   : true,
            boxBgColor  : 'rgba(255, 255, 255, 0.95)',
            effectClass : {In: 'transition.bounceIn', Out: 'transition.fadeOut'},
            OKButton    : { name: '确定', show: true }
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {
                var Htm;

                if ( settings.title ) {
                    Htm = '<div class="cloudLayerHead">'+ settings.title +'</div>';
                    L.CL.addClass('title');
                } else {
                    Htm = '';
                }
                L.CLBoxInner.append( Htm );
            },
            setBody: function() {
                var Htm;

                Htm = '<div class="cloudLayerBody">' +
                    '<div class="cloudLayerBodyInner">' +
                    '<div class="cloudLayerContainer">'+ settings.msg +'</div>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setFoot: function() {
                var Htm;

                Htm = '<div class="cloudLayerFoot">' +
                    '<div class="cloudLayerFootInner">' +
                    '<a href="javascript:;" class="cloudLayerCancel">确定</a>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setValues: function() {
                L.CLOutter.height( L.CLBoxInner.height() );
            }
        });
        L.initialize();
        return L;
    }

    /**
     * 信息提示 Tip
     * */
    CL.tip = function( msg, time ) {
        if ( !msg ) {
            return ;
        }

        var settings = {
            type       : 'tip',
            no_padding : false,
            globalClass: CL.prefix +'tip',
            layerBg    : false,
            layerBgBlur: false,
            boxBg      : true,
            boxBgBlur  : true,
            layerHeight: false,
            unique     : true,
            boxBgColor : 'rgba(0, 0, 0, 0.75)',
            effectClass: {In: 'transition.bounceInDown', Out: 'transition.bounceOutUp'},
            time       : time || 3000
        };

        var L = new BaseLayer( settings );
        $.extend( L, {
            setBlur: function() {},
            setHead: function() {
                var Htm;

                Htm = '<div class="cloudLayerHead">'+ msg +'</div>';
                L.CLBoxInner.append( Htm );
            },
            setBody: function() {},
            setFoot: function() {},
            setValues: function() {
                L.CLOutter.height( L.CLBoxInner.height() );
            },
            doOK: function() {
                L.close();
            },
            close: function( immediately ) {
                if ( immediately ) {
                    L.CL.hide();
                }
                L.CLOutter.velocity( settings.effectClass.Out );
                L.clearBlur();
                L.CL.delay( settings.delayTime , 'tip')
                    .queue('tip', function( next ){
                        L.CL.remove();
                        next();
                    })
                    .dequeue('tip');
            },
            autoClear: function() {
                setTimeout( function(){
                    L.close();
                } , settings.time );
            }
        });
        L.initialize();
        return L;
    }

    /**
     * 商品展示 Goods
     * */
    CL.goods = function( options ) {
        var settings = {
            type        : 'goods',
            no_padding  : false,
            globalClass : CL.prefix +'goods',
            layerBgColor: 'rgba(255, 255, 255, 1.80)',
            effectClass : {In: 'transition.bounceInRight', Out: 'transition.bounceOutRight'},
            OKButton    : { name: '添加', show: true },
            CancelButton: { name: '返回', show: true}
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {
                var Htm;

                if ( settings.title ) {
                    Htm = '<div class="cloudLayerHead">' +
                        '<a href="javascript:;" class="cloudLayerCancel">'+ settings.CancelButton['name'] +'</a>'+
                        '<span class="cloudLayerTitle">'+ settings.title +'</span>'+
                        '</div>';
                } else {
                    Htm = '';
                }

                L.CLBoxInner.append( Htm );
            },
            setFoot: function() {

            }
        });
        L.initialize();
        return L;
    };

    /**
     * 购物车 Cart
     * */
    CL.cart = function( options ) {
        var settings = {
            type        : 'cart',
            no_padding  : false,
            globalClass : CL.prefix +'cart',
            layerBgColor: 'rgba(255, 255, 255, 1.80)',
            effectClass : {In: 'transition.bounceInUp', Out: 'transition.bounceOutDown'},
            OKButton    : { name: '添加', show: true },
            CancelButton: { name: '返回', show: true}
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {
                var Htm;

                if ( settings.title ) {
                    Htm = '<div class="cloudLayerHead">' +
                        '<a href="javascript:;" class="cloudLayerCancel">'+ settings.CancelButton['name'] +'</a>'+
                        '<span class="cloudLayerTitle">'+ settings.title +'</span>'+
                        '<a href="javascript:;" class="cloudLayerOK">'+ settings.OKButton['name'] +'</a>'+
                        '</div>';
                } else {
                    Htm = '';
                }

                L.CLBoxInner.append( Htm );
            },
            setFoot: function() {

            }
        });
        L.initialize();
        return L;
    }

    /**
     * 图片裁剪
     * */
    CL.ImageCrop = function( options ) {
        var settings = {
            type        : 'ImageCrop',
            no_padding  : false,
            globalClass : CL.prefix +'ImageCrop',
            layerBgColor: 'rgba(255, 255, 255, 1.80)',
            effectClass : {In: 'transition.bounceInUp', Out: 'transition.bounceOutDown'},
            OKButton    : { name: '选取', show: true },
            CancelButton: { name: '取消', show: true}
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {},
            setFoot: function() {
                var Htm;

                Htm = '<div class="cloudLayerFoot">' +
                    '<a class="cloudLayerOK" href="javascript:;">'+ settings.OKButton['name'] +'</a>' +
                    '<div class="cloudLayerOpeBox"><a class="rotate-btn left" id="rotate-to-left">左转</a></div>'+
                    '<div class="cloudLayerOpeBox"><a class="rotate-btn right" id="rotate-to-right">右转</a></div>'+
                    '<a class="cloudLayerCancel" href="javascript:;">'+ settings.CancelButton['name'] +'</a>'+
                    '</div>';
                L.CLBoxInner.append( Htm );
            }
        });
        L.initialize();
        return L;
    };

    /**
     * 支付方式
     * */
    CL.pay = function( options ) {
        var settings = {
            title: '选择支付方式',
            type        : 'Pay',
            no_padding  : false,
            globalClass : CL.prefix +'Pay',
            layerBgColor: 'rgba(255, 255, 255, 1.80)',
            effectClass : {In: 'transition.bounceInUp', Out: 'transition.bounceOutDown'},
            OKButton    : { name: '立即支付', show: true },
            CancelButton: { name: '取消', show: true}
        };

        if ( options ) $.extend( settings, options );

        var L = new BaseLayer( settings );
        $.extend( L, {
            setHead: function() {
                var Htm;

                if ( settings.title ) {
                    Htm = '<div class="cloudLayerHead">' +
                        '<span class="cloudLayerTitle">'+ settings.title +'</span>'+
                        '<a href="javascript:;" class="cloudLayerCancel">'+ settings.CancelButton['name'] +'</a>'+
                        '</div>';
                } else {
                    Htm = '';
                }

                L.CLBoxInner.append( Htm );
            },
            setFoot: function() {
                var Htm;

                Htm = '<div class="cloudLayerFoot">' +
                    '<div class="cloudLayerFootInner">' +
                    '<a href="javascript:;" class="cloudLayerOK disabled">'+ settings.OKButton['name'] +'</a>'+
                    '</div>'+
                    '</div>';

                L.CLBoxInner.append( Htm );
            },
            setValues: function() {
                var hH, LH, fH;

                LH = L.CL.height();
                hH = $('.cloudLayerHead', L.CL).height();
                fH = $('.cloudLayerFoot', L.CL).height();
                L.CLBodyInner.height( LH - hH - fH );
            }
        });
        L.initialize();
        return L;
    }

} );