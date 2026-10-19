// goods.js
document.addEventListener('DOMContentLoaded', function() {
    // 从全局变量获取分类数据
    const categoryData = window.categoryData || {
        grouped: {},
        selectedId: 0,
        hasSelected: false
    };

    // 初始化wangEditor
    const { createEditor, createToolbar } = window.wangEditor;
    
    const editorConfig = {
        placeholder: '请输入商品详细描述...',
        onChange(editor) {
            const html = editor.getHtml();
            document.getElementById('descriptionContent').value = html;
            validateDescription(html);
        }
    };
    
    const editor = createEditor({
        selector: '#wangEditor',
        html: document.getElementById('descriptionContent').value,
        config: editorConfig,
        mode: 'default',
    });
    
    const toolbarConfig = {};
    
    const toolbar = createToolbar({
        editor,
        selector: '#wangEditor',
        config: toolbarConfig,
        mode: 'default',
    });
    
    // 验证描述内容
    function validateDescription(content) {
        const hasError = content.trim() === '' || content === '<p></p>';
        document.getElementById('descriptionValidation').value = hasError ? '0' : '1';
        
        const editorContainer = document.querySelector('#wangEditor');
        if (hasError) {
            editorContainer.classList.add('editor-error');
        } else {
            editorContainer.classList.remove('editor-error');
        }
    }

    // 表单提交时保存编辑器内容
    document.getElementById('productForm').addEventListener('submit', function() {
        const content = editor.getHtml();
        document.getElementById('descriptionContent').value = content;
    });

    // 图片预览功能
    const imageInput = document.querySelector('input[name="images[]"]');
    const previewContainer = document.getElementById('imagePreview');
    let uploadedImages = [];
    
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            previewContainer.innerHTML = '';
            uploadedImages = [];
            
            if (this.files) {
                const files = Array.from(this.files).slice(0, 5);
                
                files.forEach((file, index) => {
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const preview = document.createElement('div');
                            preview.className = 'image-preview-item';
                            preview.style.backgroundImage = `url(${e.target.result})`;
                            
                            const removeBtn = document.createElement('div');
                            removeBtn.className = 'remove-image';
                            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                            removeBtn.addEventListener('click', () => removeImage(index));
                            
                            preview.appendChild(removeBtn);
                            previewContainer.appendChild(preview);
                            
                            uploadedImages.push({
                                file: file,
                                url: e.target.result
                            });
                        }
                        
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    }
    
    function removeImage(index) {
        const previewItems = previewContainer.querySelectorAll('.image-preview-item');
        if (previewItems[index]) {
            previewItems[index].remove();
        }
        
        uploadedImages.splice(index, 1);
        
        const dataTransfer = new DataTransfer();
        uploadedImages.forEach(img => {
            dataTransfer.items.add(img.file);
        });
        
        imageInput.files = dataTransfer.files;
        
        previewContainer.innerHTML = '';
        uploadedImages.forEach((img, i) => {
            const preview = document.createElement('div');
            preview.className = 'image-preview-item';
            preview.style.backgroundImage = `url(${img.url})`;
            
            const removeBtn = document.createElement('div');
            removeBtn.className = 'remove-image';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', () => removeImage(i));
            
            preview.appendChild(removeBtn);
            previewContainer.appendChild(preview);
        });
    }

    // 动态加载二级分类
    const parentCategorySelect = document.getElementById('parent_category');
    if (parentCategorySelect) {
        parentCategorySelect.addEventListener('change', function() {
            const parentId = this.value;
            const subCategorySelect = document.getElementById('category_id');
            
            if (parentId > 0) {
                subCategorySelect.innerHTML = '<option value="">加载中...</option>';
                
                setTimeout(() => {
                    const categoriesForParent = categoryData.grouped[parentId] || [];
                    
                    subCategorySelect.innerHTML = categoriesForParent.length > 0 
                        ? '<option value="">请选择二级分类</option>'
                        : '<option value="">该分类下无二级分类</option>';
                    
                    categoriesForParent.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        subCategorySelect.appendChild(option);
                    });
                    
                    if (categoryData.hasSelected && categoryData.selectedId > 0) {
                        subCategorySelect.value = categoryData.selectedId;
                    }
                }, 300);
            } else {
                subCategorySelect.innerHTML = '<option value="">请先选择一级分类</option>';
            }
        });
    }

    // 动态生成区域价格输入框
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('game-region-checkbox')) {
            updateRegionPriceFields();
        }
    });

    function updateRegionPriceFields() {
        const container = document.getElementById('regionPricesContainer');
        const checkboxes = document.querySelectorAll('.game-region-checkbox:checked');
        
        container.innerHTML = '';
        
        if (checkboxes.length > 0) {
            const titleRow = document.createElement('div');
            titleRow.className = 'row mb-3';
            titleRow.innerHTML = `
                <div class="col-md-12">
                    <h6>区域价格设置</h6>
                    <small class="text-muted">为每个区域设置不同的价格，留空则使用默认价格</small>
                </div>
            `;
            container.appendChild(titleRow);
        }
        
        checkboxes.forEach(checkbox => {
            const region = checkbox.value;
            const regionKey = md5(region);
            
            const priceRow = document.createElement('div');
            priceRow.className = 'row region-price-row';
            priceRow.innerHTML = `
                <div class="col-md-4">
                    <label class="form-label">${region}价格</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" class="form-control" 
                               name="region_price_${regionKey}" 
                               placeholder="默认价格">
                        <span class="input-group-text">元</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">${region}原价</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" class="form-control" 
                               name="region_original_price_${regionKey}" 
                               placeholder="默认原价">
                        <span class="input-group-text">元</span>
                    </div>
                </div>
            `;
            
            container.appendChild(priceRow);
        });
    }

    // 大转盘功能
    let currentWheelPrizeCount = 0;
    const maxWheelPrizes = 6;

    const enableWheelCheckbox = document.getElementById('enableWheel');
    if (enableWheelCheckbox) {
        enableWheelCheckbox.addEventListener('change', function() {
            const isEnabled = this.checked;
            document.getElementById('addWheelPrizeBtn').style.display = isEnabled ? 'block' : 'none';
            
            if (isEnabled) {
                if (currentWheelPrizeCount === 0) {
                    addWheelPrize();
                }
            } else {
                for (let i = 1; i <= maxWheelPrizes; i++) {
                    const row = document.getElementById(`wheelPrizeRow${i}`);
                    if (row) {
                        row.style.display = 'none';
                    }
                    const removeBtn = document.querySelector(`.remove-wheel-prize[data-index="${i}"]`);
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                    }
                }
                currentWheelPrizeCount = 0;
            }
        });

        document.getElementById('addWheelPrizeBtn').addEventListener('click', function() {
            if (currentWheelPrizeCount < maxWheelPrizes) {
                addWheelPrize();
            } else {
                alert('最多只能添加6个奖品');
            }
        });

        function addWheelPrize() {
            currentWheelPrizeCount++;
            const row = document.getElementById(`wheelPrizeRow${currentWheelPrizeCount}`);
            if (row) {
                row.style.display = 'flex';
                const removeBtn = row.querySelector('.remove-wheel-prize');
                if (removeBtn) {
                    removeBtn.style.display = 'block';
                }
                
                if (currentWheelPrizeCount === maxWheelPrizes) {
                    document.getElementById('addWheelPrizeBtn').style.display = 'none';
                }
            }
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-wheel-prize') || e.target.closest('.remove-wheel-prize')) {
                const btn = e.target.classList.contains('remove-wheel-prize') ? e.target : e.target.closest('.remove-wheel-prize');
                const index = parseInt(btn.dataset.index);
                
                const nameInput = document.querySelector(`input[name="wheel_prize_name_${index}"]`);
                const probInput = document.querySelector(`input[name="wheel_prize_probability_${index}"]`);
                if (nameInput) nameInput.value = '';
                if (probInput) probInput.value = '';
                
                const prizeRow = document.getElementById(`wheelPrizeRow${index}`);
                if (prizeRow) prizeRow.style.display = 'none';
                btn.style.display = 'none';
                
                currentWheelPrizeCount--;
                document.getElementById('addWheelPrizeBtn').style.display = 'block';
            }
        });
    }

    // 添加商品标签
    const addTagBtn = document.getElementById('addTagBtn');
    if (addTagBtn) {
        addTagBtn.addEventListener('click', function() {
            const container = document.getElementById('tagsContainer');
            const newField = document.createElement('div');
            newField.className = 'input-group mb-2';
            newField.innerHTML = `
                <input type="text" class="form-control" name="tags[]">
                <button type="button" class="btn btn-outline-danger remove-tag"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(newField);
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-tag') || e.target.closest('.remove-tag')) {
                const btn = e.target.classList.contains('remove-tag') ? e.target : e.target.closest('.remove-tag');
                const tagGroup = btn.closest('.input-group');
                if (document.querySelectorAll('#tagsContainer .input-group').length > 1) {
                    tagGroup.remove();
                } else {
                    tagGroup.querySelector('input').value = '';
                }
            }
        });
    }

    // MD5函数用于生成唯一键
    function md5(str) {
        return str.split('').reduce((prevHash, currVal) =>
            (((prevHash << 5) - prevHash) + currVal.charCodeAt(0))|0, 0);
    }

    // 表单验证
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            let isValid = true;
            const errorMessages = [];
            
            const price = parseFloat(document.querySelector('input[name="price"]').value);
            if (price <= 0) {
                errorMessages.push('商品价格必须大于0');
                isValid = false;
            }
            
            const stock = parseInt(document.querySelector('input[name="stock"]').value);
            if (stock < 0) {
                errorMessages.push('库存不能为负数');
                isValid = false;
            }
            
            const minPurchase = parseInt(document.querySelector('input[name="min_purchase_per_user"]').value);
            if (minPurchase <= 0) {
                errorMessages.push('最低购买数量必须大于0');
                isValid = false;
            }
            
            const maxPurchaseInput = document.querySelector('input[name="max_purchase_per_user"]');
            if (maxPurchaseInput.value) {
                const maxPurchase = parseInt(maxPurchaseInput.value);
                if (maxPurchase < minPurchase) {
                    errorMessages.push('最大购买数量不能小于最低购买数量');
                    isValid = false;
                }
            }
            
            const imageInput = document.querySelector('input[name="images[]"]');
            if (!imageInput.files || imageInput.files.length === 0) {
                errorMessages.push('请至少上传一张商品图片');
                isValid = false;
            }
            
            const gameRegions = document.querySelectorAll('input[name="game_region[]"]:checked');
            if (gameRegions.length === 0) {
                errorMessages.push('请至少选择一个游戏大区');
                isValid = false;
            }
            
            const parentCategory = document.getElementById('parent_category').value;
            const subCategory = document.getElementById('category_id').value;
            if (!parentCategory || !subCategory) {
                errorMessages.push('请选择完整分类');
                isValid = false;
            }
            
            const descriptionValidation = document.getElementById('descriptionValidation').value;
            if (descriptionValidation === '0') {
                errorMessages.push('商品说明不能为空');
                isValid = false;
                
                const editorContainer = document.querySelector('#wangEditor');
                if (editorContainer) {
                    editorContainer.classList.add('editor-error');
                }
            }
            
            if (enableWheelCheckbox && enableWheelCheckbox.checked) {
                let totalProbability = 0;
                let prizeCount = 0;
                
                for (let i = 1; i <= maxWheelPrizes; i++) {
                    const nameInput = document.querySelector(`input[name="wheel_prize_name_${i}"]`);
                    const probInput = document.querySelector(`input[name="wheel_prize_probability_${i}"]`);
                    
                    if (nameInput && probInput) {
                        const name = nameInput.value;
                        const probability = parseFloat(probInput.value);
                        
                        if (name && !isNaN(probability)) {
                            totalProbability += probability;
                            prizeCount++;
                        }
                    }
                }
                
                if (prizeCount > 0 && Math.abs(totalProbability - 1) > 0.0001) {
                    errorMessages.push(`所有奖品的概率总和应为1，当前总和为：${totalProbability}`);
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show mb-4';
                errorAlert.innerHTML = `
                    <strong><i class="fas fa-exclamation-circle me-2"></i>表单验证失败!</strong>
                    <ul class="mb-0 mt-2">
                        ${errorMessages.map(msg => `<li>${msg}</li>`).join('')}
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const oldAlert = document.querySelector('.alert.alert-danger');
                if (oldAlert) {
                    oldAlert.remove();
                }
                
                const container = document.querySelector('.container.py-4');
                container.insertBefore(errorAlert, container.firstChild);
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }
});