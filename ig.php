<strong><span style="font-size:18px;">
<?php

    function GetStockPrice($StockCode){
        $homepage = file_get_contents('http://hq.sinajs.cn/list='.$StockCode);
        if (substr($StockCode,0,2)=='gb'){
            return explode(',', $homepage)[1];}
        else{
            return explode(',', $homepage)[3];}
    }
    
    function GetExchangePrice($ExchangeCode){
        $homepage = file_get_contents('http://webforex.hermes.hexun.com/forex/quotelist?code=FOREX'.$ExchangeCode.'&column=Code,Price');
        $ExPrice=substr($homepage,stripos($homepage,',')+1,5)/10000;
        return $ExPrice;
    }
    
    function Buy(){
        global $conn;
        global $USDCNY;
        $arr=func_get_args();
        $ss=GetStockPrice($arr[3]);
        //$ss=16.80;
        $sql="
        UPDATE igfund SET 
        IA=$arr[1],
        GD='$arr[2]',
        CD='$arr[3]',
        BP=$ss,
        GT=CASE
        WHEN LEFT(CD,2)='gb' THEN '美股'
        WHEN LEFT(CD,2)='sh' THEN 'A股'
        END,
        BA=CASE
        WHEN LEFT(CD,2)='gb' THEN floor(IA/100/BP)*100
        WHEN LEFT(CD,2)='sh' THEN floor(IA*$USDCNY/100/BP)*100
        END,
        BD='2020-04-01',
        FH=CASE
        WHEN LEFT(CD,2)='gb' THEN IA-BP*BA
        WHEN LEFT(CD,2)='sh' THEN IA-BP*BA/$USDCNY
        END
        WHERE CN='$arr[0]'";
        $conn->query($sql);
    }
    
    function UpdatePrice(){
        global $conn;
        global $USDCNY;
        $sql_qry="SELECT CD,CP FROM igfund WHERE CD IS NOT NULL";
        $duration = $conn->query($sql_qry);
        $StockCode='';
        $W='';
        while($record = $duration->fetch_array()){
            $ArrCD[]=$record['CD'];
            $StockCode .= $record['CD'].',';
            $W.="'".$record['CD']."',";
        }
        
        $homepage = file_get_contents('http://hq.sinajs.cn/list='.$StockCode);
        $StockArray=explode(';', $homepage);
        
        $W=substr($W,0,-1);
        $sql = "UPDATE igfund SET CP = CASE CD "; 
        for ($i=0;$i<count($ArrCD);$i++){
            $cp=explode(',',$StockArray[$i]);
            if (substr($ArrCD[$i],0,2)=='gb'){
                $cp=$cp[1]; 
            }
            else{
                $cp=$cp[3]; 
            }
            $sql .= sprintf("WHEN '%s' THEN '%.2f' ", $ArrCD[$i], $cp);
        }
        $sql .= "END,LV = CASE GT WHEN 'A股' THEN round(CP*BA/$USDCNY,2) WHEN '美股' THEN CP*BA END,FV=LV+FH,FI=round(FV/IA,4),PF=FV-IA WHERE CD IN ($W)"; 
        $conn->query($sql);
    }

    function ShowTable($table_name){
        global $USDCNY;
        global $conn;
        $conn = new mysqli("localhost", "tongs982_AuEjrL", "Morning123", "tongs982_dtest");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        } 
        $conn->query("set names utf8");
        
        //Buy('晶晶',104710,'北大荒','sh600598');
        
        UpdatePrice();
   

        $conn->query("update igfund set FH=IA,FV=IA,FI=1.0000 where GD is NULL");
        
        $sql_qry="SELECT SUM(fv) AS count FROM igfund";
        $duration = $conn->query($sql_qry);
        while($record = $duration->fetch_array()){
            $total = $record['count'];
        }
        
        $sql_qry="SELECT SUM(fv)/SUM(IA) AS JZ FROM igfund";
        $duration = $conn->query($sql_qry);
        while($record = $duration->fetch_array()){
            $jz = $record['JZ'];
        }
        
        $tt="
            CN as '姓名',
            PF as '获利($)',
            FI as '净值',
            IA as '投资额($)',
            GD as '名称',
            BP as '买入价',
            BA as '买入数量',
            CP as '当前价格',
            FH as '头寸($)'
            ";
            
        if($res=$conn->query("select $tt from $table_name order by FI desc")){
            $rows=$res->num_rows;//rows
            $colums=$res->field_count;//fields
            
            echo "IG共同基金目前拥有用户".$rows."名, 累计市值 $".$total.", 综合净值 ".$jz.", 当前外汇牌价：1美元=$".$USDCNY."人民币<br/>";
            //echo '<table class="gridtable"><tr>';
            //echo '<table class="comicGreen"><tr>';
            echo '<table class="blueTable"><tr>';
            while ($fieldinfo = $res -> fetch_field()) {
                echo "<th>$fieldinfo->name</th>";
            }
            echo "</tr>";
            while($row=$res->fetch_row()){
                echo "<tr>";
                for($i=0; $i<$colums; $i++){
                    echo "<td>$row[$i]</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            $res -> free_result();
        }
        $conn -> close();
    }
    
    global $conn;
    global $USDCNY;
    $USDCNY=GetStockPrice('USDCNY');
    echo '<link href="./table.css" rel="stylesheet" type="text/css" />';
    ShowTable("igfund");
    //echo '<iframe src="https://gisanddata.maps.arcgis.com/apps/opsdashboard/index.html#/bda7594740fd40299423467b48e9ecf6" frameborder="0" name="mainShow" style="width:100%;height:500px;"></iframe>';
?></span></strong>
