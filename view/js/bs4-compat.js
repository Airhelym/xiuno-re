/**
 * Bootstrap 4 兼容垫片 (BS4 Compatibility Shim)
 * 
 * 此文件用于兼容 Bootstrap 4 时代的插件。
 * 主程序核心功能不依赖此文件，仅在需要兼容旧插件时加载。
 * 
 * Bootstrap 5 变化要点:
 * - data-toggle → data-bs-toggle
 * - data-dismiss → data-bs-dismiss
 * - card-block → card-body
 * - float-left → float-start
 * - float-right → float-end
 * - .alert() 方法已移除
 * 
 * @author xiunore
 * @version 1.0
 */

// ============================================================================
// 数据属性兼容层 (Data Attribute Compatibility Layer)
// ============================================================================

(function() {
    'use strict';

    // 确保在 DOM ready 后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBS4Compat);
    } else {
        initBS4Compat();
    }

    function initBS4Compat() {
        // 如果 Bootstrap 5 已经处理过某些功能，跳过
        if (typeof bootstrap !== 'undefined') {
            patchBS4DataAttributes();
            patchBS4AlertMethod();
            patchBS4DropdownToggle();
        }
    }

    /**
     * 修补 Bootstrap 4 的 data-toggle 属性
     * 自动将 data-toggle 转换为 data-bs-toggle
     */
    function patchBS4DataAttributes() {
        // Modal
        $('[data-toggle="modal"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle')) {
                $el.attr('data-bs-toggle', 'modal');
            }
        });

        // Collapse
        $('[data-toggle="collapse"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle')) {
                $el.attr('data-bs-toggle', 'collapse');
            }
        });

        // Dropdown
        $('[data-toggle="dropdown"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle')) {
                $el.attr('data-bs-toggle', 'dropdown');
            }
        });

        // Tab
        $('[data-toggle="tab"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle')) {
                $el.attr('data-bs-toggle', 'tab');
            }
        });

        // Pill
        $('[data-toggle="pill"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle')) {
                $el.attr('data-bs-toggle', 'pill');
            }
        });

        // Tooltip (Bootstrap 5 使用 data-bs-xxx)
        $('[data-toggle="tooltip"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle') && !$el.attr('data-bs-original-title')) {
                var title = $el.attr('title') || $el.attr('data-original-title') || '';
                $el.attr('data-bs-toggle', 'tooltip');
                $el.attr('data-bs-original-title', title);
                // 尝试初始化 tooltip
                try {
                    var tooltip = new bootstrap.Tooltip(this);
                    $el.data('bs.tooltip', tooltip);
                } catch (e) {
                    // 如果 Bootstrap Tooltip 未加载，静默忽略
                }
            }
        });

        // Popover
        $('[data-toggle="popover"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-toggle') && !$el.attr('data-bs-original-title')) {
                var title = $el.attr('title') || '';
                var content = $el.attr('data-content') || '';
                $el.attr('data-bs-toggle', 'popover');
                $el.attr('data-bs-original-title', title);
                $el.attr('data-bs-content', content);
                try {
                    var popover = new bootstrap.Popover(this);
                    $el.data('bs.popover', popover);
                } catch (e) {
                    // 静默忽略
                }
            }
        });
    }

    /**
     * 修补 Bootstrap 4 的 data-dismiss 属性
     */
    function patchBS4DismissAttributes() {
        $('[data-dismiss="modal"]').each(function() {
            var $el = $(this);
            if (!$el.attr('data-bs-dismiss')) {
                $el.attr('data-bs-dismiss', 'modal');
            }
        });
    }

    /**
     * 修补 Bootstrap 4 的 $.fn.alert 方法
     * Bootstrap 5 移除了此方法
     */
    function patchBS4AlertMethod() {
        if ($.fn.alert && typeof $.fn.alert === 'function') {
            // Bootstrap 5 已经有 alert，如果需要兼容旧调用，可以保留
            return;
        }

        // 如果 alert 方法不存在，提供兼容实现
        // 注意：这个实现只是用于兼容性，实际功能可能受限
        $.fn.alert = function(option) {
            var $this = $(this);
            
            if (typeof option === 'string') {
                // 支持 .alert('close') 语法
                if (option === 'close' || option === 'dispose') {
                    $this.find('[data-bs-dismiss="modal"], [data-dismiss="modal"]').trigger('click');
                }
                return $this;
            }
            
            return $this;
        };
    }

    /**
     * 修补 Bootstrap 4 的 dropdown toggle 事件
     */
    function patchBS4DropdownToggle() {
        // Bootstrap 5 使用 show.bs.dropdown, shown.bs.dropdown, hide.bs.dropdown, hidden.bs.dropdown
        // 大部分情况下已经兼容，这里做一些额外的处理
    }

})();

// ============================================================================
// 类名兼容层 (Class Name Compatibility)
// ============================================================================

/**
 * BS4 到 BS5 类名映射
 * 用于运行时类名替换
 */
var BS4_TO_BS5_CLASS_MAP = {
    'float-left': 'float-start',
    'float-right': 'float-end',
    'text-left': 'text-start',
    'text-right': 'text-end',
    'border-left': 'border-start',
    'border-right': 'border-end',
    'rounded-left': 'rounded-start',
    'rounded-right': 'rounded-end',
    'card-block': 'card-body'
};

/**
 * 批量替换类名
 * @param {jQuery} $elements - jQuery 元素集
 * @param {Object} map - 类名映射表
 */
function bs4ReplaceClasses($elements, map) {
    $elements.each(function() {
        var $el = $(this);
        var classes = this.className.split(/\s+/);
        var newClasses = [];
        
        for (var i = 0; i < classes.length; i++) {
            var cls = classes[i];
            if (map[cls]) {
                newClasses.push(map[cls]);
            } else {
                newClasses.push(cls);
            }
        }
        
        this.className = newClasses.join(' ');
    });
}

/**
 * 初始化 BS4 类名兼容
 * 自动替换页面中的旧类名
 */
$(function() {
    // 替换常见的 BS4 类名
    var $all = $('body *');
    bs4ReplaceClasses($all, BS4_TO_BS5_CLASS_MAP);
});

// ============================================================================
// Card 组件兼容层
// ============================================================================

/**
 * BS4 card-block 到 BS5 card-body 兼容
 * Bootstrap 5 使用 card-body 而非 card-block
 */
$(function() {
    // 查找使用 card-block 的元素并添加 card-body 类
    $('.card-block').addClass('card-body');
});

// ============================================================================
// 向后兼容的 jQuery 扩展
// ============================================================================

/**
 * 向后兼容的 modal 方法
 * Bootstrap 4 使用 .modal('show'), .modal('hide')
 * Bootstrap 5 使用 bootstrap.Modal 实例方法
 */
$.fn.modalBS4 = function(action, options) {
    var $modal = $(this);
    
    if (action === 'show') {
        var modal = bootstrap.Modal.getOrCreateInstance(this, options);
        modal.show();
        return this;
    }
    
    if (action === 'hide') {
        var modal = bootstrap.Modal.getInstance(this);
        if (modal) modal.hide();
        return this;
    }
    
    if (action === 'toggle') {
        var modal = bootstrap.Modal.getOrCreateInstance(this, options);
        modal.toggle();
        return this;
    }
    
    if (action === 'dispose') {
        var modal = bootstrap.Modal.getInstance(this);
        if (modal) modal.dispose();
        return this;
    }
    
    return this;
};

// ============================================================================
// Alert 组件兼容层
// ============================================================================

/**
 * $.fn.alert 兼容方法
 * Bootstrap 4: $.alert(message), element.alert('close')
 * Bootstrap 5: 已移除静态方法，需要手动创建 Modal
 */
if (typeof $.alert === 'undefined') {
    /**
     * 创建提示对话框 (兼容 Bootstrap 4 语法)
     * @param {string} message - 提示消息
     * @param {function} callback - 关闭后的回调
     * @returns {jQuery} Modal 元素
     */
    $.alert = function(message, callback) {
        var s = '<div class="modal fade" tabindex="-1">' +
            '<div class="modal-dialog modal-sm">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' + (typeof lang !== 'undefined' ? lang.tips_title : '提示') + '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body"><p>' + message + '</p></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + (typeof lang !== 'undefined' ? lang.close : '关闭') + '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        var $modal = $(s).appendTo('body');
        var modal = new bootstrap.Modal($modal[0]);
        modal.show();
        
        $modal.on('hidden.bs.modal', function() {
            $modal.remove();
            if (callback) callback();
        });
        
        return $modal;
    };
}

/**
 * 创建确认对话框 (兼容 Bootstrap 4 语法)
 * @param {string} message - 确认消息
 * @param {function} okCallback - 确认后的回调
 * @param {Object} options - 配置选项
 * @returns {jQuery} Modal 元素
 */
if (typeof $.confirm === 'undefined') {
    $.confirm = function(message, okCallback, options) {
        options = options || {};
        
        var title = options.title || (typeof lang !== 'undefined' ? lang.confirm_title : '确认');
        var body = options.body || '<p>' + message + '</p>';
        
        var s = '<div class="modal fade" tabindex="-1">' +
            '<div class="modal-dialog ' + (options.size || '') + '">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' + title + '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body fs-5">' + body + '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-primary confirm-ok">' + (typeof lang !== 'undefined' ? lang.confirm : '确认') + '</button>' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + (typeof lang !== 'undefined' ? lang.close : '取消') + '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        var $modal = $(s).appendTo('body');
        var modal = new bootstrap.Modal($modal[0]);
        modal.show();
        
        $modal.find('.confirm-ok').on('click', function() {
            modal.hide();
            if (okCallback) okCallback();
        });
        
        $modal.on('hidden.bs.modal', function() {
            $modal.remove();
        });
        
        return $modal;
    };
}

// ============================================================================
// 导出公共接口
// ============================================================================

window.bs4compat = {
    version: '1.0',
    replaceClasses: bs4ReplaceClasses,
    BS4_TO_BS5_CLASS_MAP: BS4_TO_BS5_CLASS_MAP
};
