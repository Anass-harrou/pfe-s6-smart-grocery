<?php 
// déclaration de la table chaque mois est une clé et a comme valeur les jours du mois. 
$mois = array (
"janvier"=>range(1,31),
"fevrier"=>range(1,28),
"mars"=>range(1,31),
"avril"=>range(1,31),
"mai"=>range(1,31),
"juin"=>range(1,31),
) ; 
// fonction d'affichage 
function affiche_mois($mois){
echo "<table border =2> 
<tr><td>Lu</td><td>Mr</td><td>Me</td><td>Je</td><td>Ve</td><td>Sa</td><td
>Di</td></tr>";
echo "<tr>"; 
foreach ($mois as $j){
echo " <td> $j </td>";
if($j%7==0) echo "</tr><tr>";
}
echo "</tr>"; 
echo "</table>"; 
}
// appel de la fonction 
affiche_mois ($mois["fevrier"]) ; 
?>
