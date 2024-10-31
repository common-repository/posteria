<?php function wo_admin_manage_clients_page()
{
	wp_enqueue_style('wo_admin');
	wp_enqueue_script('wo_admin');
?>
	<!-- <div class="wrap">

		<h2><?php _e('Clients', 'wp-oauth'); ?>
			<a class="add-new-h2 "
			   href="<?php //echo admin_url( 'admin.php?page=wo_add_client' ); 
						?>"
			   title="Batch"><?php //_e( 'Add New Client', 'wp-oauth' ); 
								?></a>
		</h2>

		<div class="section group">
			<div class="col span_4_of_6">
				<?php
				// $CodeTableList = new WO_Table();
				// $CodeTableList->prepare_items();
				// $CodeTableList->display();
				?>
			</div>

		</div>

	</div> -->
	<div class="row">

		<div class="text-center">
			<img class="width-100" src="https://growthhackingfrance.com/wp-content/uploads/2022/07/img_plugin_posteria_2.png">
		</div>
		<div class="bloc-title">Votre plugin est activé vous pouvez retourner sur Posteria</div>
	</div>
<?php
}
