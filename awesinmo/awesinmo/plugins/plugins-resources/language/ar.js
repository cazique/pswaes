AppGiniPlugin.language = AppGiniPlugin.language || {};
AppGiniPlugin.language.ar = $j.extend(AppGiniPlugin.language.ar, {
	rtl: true,

	FAILED: 'فشل',
	OK: 'موافق',
	SKIPPED: 'تم تخطيه',

	copying_folder: 'جارى نسخ المجلد %src% ...',
	failed_to_copy_n_subfolders_from: 'فشل نسخ %num_errors% مجلد/ملف من %src%.',
	folder_x_copied: 'تم نسخ المجلد %src% بنجاح.',
	Error: 'خطأ:',
	Back: 'رجوع',
	couldnt_create_projects_dir: 'لم يمكن إنشاء مجلد المشاريع.<br>الرجاء إنشاء مجلد \'projects\' داخل مجلد الإضافات',
	change_permissions_projects_dir: 'الرجاء تغيير صلاحيات مجلد \'projects\' لتكون قابلة للكتابة.',
	invalid_project_file_name: 'اسم ملف المشروع غير صالح',
	path_to_appgini_app: 'المسار إلى تطبيق AppGini المستهدف',
	please_wait: 'الرجاء الانتظار ...',

	specify_full_path_appgini_app: 'حدد المسار الكامل لتطبيق AppGini الذي تريد تثبيت الكود المولد إليه. مثال: ',
	Continue: 'متابعة',
	drag_appgini_axp_here: 'اسحب ملف مشروع AppGini (*.axp) هنا لفتحه.',
	or_click_open_upload: 'أو انقر لفتح نموذج الرفع.',
	or_open_project_uploaded: 'أو افتح مشروع قمت برفعه من قبل',
	projects_found: 'المشاريع الموجودة:',
	click_project_to_load: 'انقر على مشروع لتحميله',
	are_you_sure_delete_axp: 'هل أنت متأكد أنك تريد حذف هذا الملف؟',
	file_uploaded_success: 'تم رفع الملف بنجاح.',
	project_exists_renamed: 'اسم المشروع موجود بالفعل، تم إعادة تسمية الملف إلى %new_name%.',
	must_upload_axp: 'يجب رفع ملف (.axp)',
	couldnt_delete_axp: 'لم يمكن حذف هذا الملف.',
	download_axp: 'تنزيل هذا الملف',
	delete_axp: 'حذف هذا الملف',

	valid_path: 'مسار صالح',
	invalid_path: 'مسار غير صالح',
})
