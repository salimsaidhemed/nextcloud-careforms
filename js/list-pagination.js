(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) { root.CareForms = root.CareForms || {}; root.CareForms.ListPagination = api; }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    function query(page, pageSize, search) {
        var params = new URLSearchParams();
        params.set('page', String(Math.max(1, Number(page) || 1)));
        params.set('pageSize', String(Math.max(1, Math.min(100, Number(pageSize) || 25))));
        if (String(search || '').trim()) params.set('search', String(search).trim());
        return params.toString();
    }

    function payload(value) {
        if (Array.isArray(value)) return {items:value,pagination:{page:1,pageSize:value.length||25,total:value.length,totalPages:value.length?1:0,hasPrevious:false,hasNext:false,previousPage:null,nextPage:null}};
        return {items:Array.isArray(value && value.items)?value.items:[],pagination:value && value.pagination ? value.pagination : {page:1,pageSize:25,total:0,totalPages:0,hasPrevious:false,hasNext:false,previousPage:null,nextPage:null}};
    }

    function render(mount, metadata, noun, onPage, onPageSize) {
        if (!mount) return;
        var meta=metadata||{}, page=Number(meta.page)||1, pages=Number(meta.totalPages)||0, total=Number(meta.total)||0;
        mount.innerHTML='';mount.className='careforms-pagination';mount.setAttribute('aria-label',(noun||'Items')+' pagination');
        var status=document.createElement('span');status.className='careforms-pagination-status';status.setAttribute('aria-live','polite');status.textContent=(pages?'Page '+page+' of '+pages:'No pages')+' · '+total+' '+(noun||'item')+(total===1?'':'s');
        var previous=document.createElement('button');previous.type='button';previous.className='careforms-secondary-button';previous.textContent='Previous';previous.disabled=!meta.hasPrevious;previous.addEventListener('click',function(){onPage(Number(meta.previousPage)||Math.max(1,page-1));});
        var next=document.createElement('button');next.type='button';next.className='careforms-secondary-button';next.textContent='Next';next.disabled=!meta.hasNext;next.addEventListener('click',function(){onPage(Number(meta.nextPage)||page+1);});
        var sizeLabel=document.createElement('label');sizeLabel.textContent='Per page';var size=document.createElement('select');[10,25,50,100].forEach(function(value){var option=document.createElement('option');option.value=value;option.textContent=value;option.selected=Number(meta.pageSize)===value;size.appendChild(option);});size.addEventListener('change',function(){onPageSize(Number(size.value));});sizeLabel.appendChild(size);
        mount.appendChild(previous);mount.appendChild(status);mount.appendChild(next);mount.appendChild(sizeLabel);
    }

    function debounce(callback, delay) {
        var timer=null;return function(){var args=arguments;clearTimeout(timer);timer=setTimeout(function(){callback.apply(null,args);},delay||250);};
    }

    return {query:query,payload:payload,render:render,debounce:debounce};
}));
