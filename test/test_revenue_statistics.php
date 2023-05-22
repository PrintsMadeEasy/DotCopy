<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();


$domainsForReport = array(1,2,6,7,8,9,10,12,13,14);


print "Date,";

foreach($domainsForReport as $thisDomainID){
	print Domain::getDomainKeyFromID($thisDomainID) . ",";
}
print "\n";

$startMonth = 8;
$startDay = 1;
$spacingDays = 7;

$revenueTotalsArr = array();
$profitTotalsArr = array();

for($i=1; $i<4000; $i++){

	$start_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays * $i), 2010);
	$end_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays + $spacingDays * $i), 2010);

	if($end_timestamp > time())
		break;
	
		
	print date("n/j/Y", $start_timestamp) . ",";
	
	
	$start_timestamp = "\"" . DbCmd::FormatDBDateTime($start_timestamp) . "\"";
	$end_timestamp = "\"" . DbCmd::FormatDBDateTime($end_timestamp) . "\"";

	foreach($domainsForReport as $thisDomainID){
		
		if(!isset($revenueTotalsArr[$thisDomainID])){
			$revenueTotalsArr[$thisDomainID] = 0;
			$profitTotalsArr[$thisDomainID] = 0;
		}
		
			
		// Keep adding to these vars as we loop through the query
		$order_income_total = 0;
		$totalRetailCharges = 0;
		$totalRetailChargesNonTaxable = 0;
		$totalRetailChargesTaxable = 0;
		$total_orders = 0;
		$total_projects = 0;
		$total_reprints = 0;
		$tax_total_for_subtotals = 0;
		$shipping_tax_total = 0;
		$sum_project_vendor_subtotals = 0;
		$sum_reprint_vendor_subtotals = 0;
		$sum_reprint_customer_subtotals  = 0;
		$project_customer_subtotal_total = 0;
		$reprint_income_total = 0;
		$project_income_total = 0;
		$shipping_quotes_total = 0;
		$shipping_quotes_total_nontaxable = 0;
		$shipping_quotes_total_taxable = 0;
		$order_totals_invoiced = 0;
		$project_income_total_invoiced = 0;
		$OrderIDtrackerHash = 0;
		

		$query = "SELECT orders.ID AS OrderID, users.Name, users.Company, projectsordered.OrderDescription, 
			UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, projectsordered.ID AS ProjectID,  
			projectsordered.CustomerSubtotal, projectsordered.CustomerTax, 
			projectsordered.CustomerDiscount, projectsordered.OptionsDescription, projectsordered.Status, 
			projectsordered.RePrintLink, orders.ShippingQuote, orders.BillingType, orders.ShippingTax 
			FROM (orders USE INDEX (orders_DateOrdered) INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID) 
			INNER JOIN users ON orders.UserID = users.ID WHERE 
			orders.DomainID=$thisDomainID AND 
			(orders.DateOrdered BETWEEN " . $start_timestamp . " AND " . $end_timestamp . ") ";
		
		$query .= " ORDER BY orders.DateOrdered DESC";
		
		$dbCmd->Query($query);
		
		$empty_projects=true;
		$empty_reprints = true;
		$empty_adjustments = true;
		
		while ($row = $dbCmd->GetRow()){
		
		
			$order_number = $row["OrderID"];
			$user_name = $row["Name"];
			$user_company = $row["Company"];
			$order_summary = $row["OrderDescription"];
			$date_ordered = $row["DateOrdered"];
			$project_id = $row["ProjectID"];
			$customer_subtotal = $row["CustomerSubtotal"];
			$Project_tax = $row["CustomerTax"];
			$CustDisc = $row["CustomerDiscount"];
			$OptionsDescription = $row["OptionsDescription"];
			$ProjectStatus = $row["Status"];
			$RePrintLink = $row["RePrintLink"];
			$CustomerShippingQuote = $row["ShippingQuote"];
			$BillingType = $row["BillingType"];
			$ShippingTax = $row["ShippingTax"];
		
			if($CustDisc == "")
				$CustDisc = 0;
		
		
			// Skip this project if it has been canceled
			if($ProjectStatus == "C")
				continue;
		
	
		
			// This will get the subtotal for all vendors associates with this project...
			// ... unless the person viewing this page is a vendor.   Then it will only get there total.
			$vendor_subtotal_all = ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $project_id);
		
			$CustomerDiscount = round($customer_subtotal * $CustDisc, 2);
		
			// This is a way to add up results from join... for each unique order (not project)
			if(!isset($OrderIDtrackerHash[$order_number])){
				$OrderIDtrackerHash[$order_number] = true;
				$total_orders++;
				$shipping_quotes_total += $CustomerShippingQuote;
		
				$shipping_tax_total += $ShippingTax;
		
				// For corporate billing, add the Shipping Quote to the total
				if($BillingType == "C")
					$order_totals_invoiced += $CustomerShippingQuote;
				if($Project_tax == 0)
					$shipping_quotes_total_nontaxable += $CustomerShippingQuote;
				else
					$shipping_quotes_total_taxable += $CustomerShippingQuote;
		
			}
		
			// Add up main totals
			$order_income = $customer_subtotal - $CustomerDiscount - $vendor_subtotal_all;
			$order_income_total += $order_income;
			$totalRetailCharges += $customer_subtotal - $CustomerDiscount;
			$tax_total_for_subtotals += $Project_tax;
			
		}
		
		
		
		$VendorAdjustmentTotal = 0;
		$CustomerAdjusmentTotal = 0;
		
		
		// Get list adjustments
		$query = "SELECT OrderID, CustomerAdjustment, VendorAdjustment, Description, FromUserID, 
				UNIX_TIMESTAMP(balanceadjustments.DateCreated) AS DateCreated, balanceadjustments.VendorID FROM balanceadjustments USE INDEX (balanceadjustments_DateCreated) 
				INNER JOIN orders ON balanceadjustments.OrderID = orders.ID
				WHERE DomainID=$thisDomainID AND 
				(balanceadjustments.DateCreated BETWEEN " . $start_timestamp . " AND " . $end_timestamp . ") ";
		$dbCmd->Query($query);
		
		$VendorAdjusmentTotal = 0;
		$CustomerAdjusmentTotal = 0;
		$counter = 0;
		
		while($row = $dbCmd->GetRow()){
		
			$thisOrderID = $row["OrderID"];
			$customerAdjust = $row["CustomerAdjustment"];
			$vendorAdjust = $row["VendorAdjustment"];
			$adjustedByUserID = $row["FromUserID"];
			$Adjst_desc = $row["Description"];
			$Adjustcreated = $row["DateCreated"];
			$ThisVendorID = $row["VendorID"];
			
			$CustomerAdjusmentTotal += $customerAdjust;
			$VendorAdjusmentTotal += $vendorAdjust;
		}
		
		$adjustments_profit = $CustomerAdjusmentTotal - $VendorAdjusmentTotal;
			
		
		
		// Get the shipping totals for all shipments completed within this period
		// We can not get the Value with a JOIN, because there may be many Projects associated with a shipment.
		$dbCmd->Query("SELECT DISTINCT shipments.ID FROM 
						(shipments USE INDEX (shipments_DateShipped) INNER JOIN shipmentlink ON shipmentlink.ShipmentID = shipments.ID)
						INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID
						WHERE DomainID=$thisDomainID AND 
						(shipments.DateShipped BETWEEN " . $start_timestamp . " AND " . $end_timestamp . ") ");
		$allShipmentIDs = $dbCmd->GetValueArr();
		
		$FreightCharges = 0;
		$PackageWeights = 0;
		foreach ($allShipmentIDs as $thisShipID){
			
			$dbCmd->Query("SELECT ShippingCost, PackageWeight FROM shipments WHERE ID=$thisShipID");
			$row = $dbCmd->GetRow();
			$FreightCharges += $row["ShippingCost"];
			$PackageWeights += $row["PackageWeight"];
		}
				
		
		
		
		
			
		$dbCmd->Query("SELECT SUM(CommissionFee) FROM charges INNER JOIN orders ON charges.OrderID = orders.ID 
				WHERE DomainID=$thisDomainID AND 
				(StatusDate BETWEEN " . $start_timestamp . " AND " . $end_timestamp . ") ");
		$MerchantFees = number_format($dbCmd->GetValue(), 2, '.', '');
		
		
			
		

		
		
		$dbCmd->Query("SELECT SUM(ChargeAmount) AS LoyaltyCharges, SUM(RefundAmount) AS LoyaltyRefunds FROM loyaltycharges WHERE 
						DomainID=$thisDomainID  AND 
						Date BETWEEN " . $start_timestamp . " AND " . $end_timestamp);
		$loyaltyRow = $dbCmd->GetRow();
		$loyaltyCharges = $loyaltyRow["LoyaltyCharges"];
		$loyaltyRefunds = $loyaltyRow["LoyaltyRefunds"];
		$loyaltyBalance = $loyaltyCharges - $loyaltyRefunds;
		
		
		
		$total_shipping_income = $shipping_quotes_total - $FreightCharges;
		$gross_sales_total = $tax_total_for_subtotals + $shipping_tax_total + $shipping_quotes_total + $adjustments_profit + $totalRetailCharges + $loyaltyBalance;
		
		
		$Total_income = $total_shipping_income + $adjustments_profit + $order_income_total - $MerchantFees + $loyaltyBalance;

		$revenueTotalsArr[$thisDomainID] += $gross_sales_total;
		$profitTotalsArr[$thisDomainID] += $Total_income;
		
		//print $revenueTotalsArr[$thisDomainID] . ",";
		print $profitTotalsArr[$thisDomainID] . ",";
	}
	print "\n";

	flush();
}






