{footer_script require="jquery"}

{/footer_script}

{html_style}

{/html_style}

<div class="titrePage">
    <h2></h2>
</div>
<div id="helpContent">
    <p></p>

    <fieldset style="text-align:left">
        <legend>导入数据</legend>
    </fieldset>
</div>
<main class="main">
    {if isset($result.total) && $result.total>0  }
        <div class="result">
            <span class="total">  产品总数:{$result.total}</span>
            <span class="success_quantity">  成功总数:{$result.success_quantity}</span>
        </div>
        <div class="failed_sku">
            <div>失败的sku</div>
            {foreach from=$result.failed_sku  key=k item=item}
                <div>{$k} -- {$item}</div>
            {/foreach}
        </div>
    {else}
        <div class="form">
            <form method="post" enctype="multipart/form-data">
                <div><label for="site">要导入的站点:</label>
                    <select name="site_id">
                        <option value="0">请选择站点</option>
                        {foreach $sites as $item}
                            <option value="{$item.id}">{$item.name}</option>
                        {/foreach}
                    </select>
                </div>
                <div>
                    <label for="file-upload">Choose a file:</label>
                    <input type="file" id="file-upload" name="file" accept=".csv, text/csv">
                    <button type="submit">Upload</button>
                </div>
            </form>
        </div>
    {/if}

</main>