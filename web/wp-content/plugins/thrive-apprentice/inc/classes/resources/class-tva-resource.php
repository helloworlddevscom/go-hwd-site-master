<?php

/**
 * Class TVA_Resource
 *
 * @property string $id           wp post ID
 * @property int    $lesson_id    WP post_parent field
 * @property string $title        resource title (wp's post_title field)
 * @property int    $order        order (sorted ASC by this field)
 * @property string $type         resource type (url, content, file)
 * @property string $content      resource description
 * @property string $icon         resource icon (svg html)
 * @property array  $config       configuration array. Stores URL, attachment, or post depending on type
 */
class TVA_Resource implements JsonSerializable {

	/**
	 * Lesson Resources post type
	 */
	const POST_TYPE = 'tva_resource';

	/**
	 * List of all available icons based on file type / URL
	 *
	 * @var array
	 */
	public static $icons = array(
		'default' => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M282.687 93c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993V70.734c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm0-1.875h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674V65.812c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm.938-20.625H278v-5.566c.156.039.293.117.41.234l4.922 4.922c.117.117.215.254.293.41z" transform="translate(-263 -63)"/></g></g></svg>',
		'text'    => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M125.687 86c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993V63.734c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H121v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674V58.812c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zM122.172 71c.195 0 .361-.068.498-.205.137-.137.205-.303.205-.498v-.469c0-.195-.068-.361-.205-.498-.137-.137-.303-.205-.498-.205h-9.844c-.195 0-.361.068-.498.205-.137.137-.205.303-.205.498v.469c0 .195.068.361.205.498.137.137.303.205.498.205h9.844zm0 3.75c.195 0 .361-.068.498-.205.137-.137.205-.303.205-.498v-.469c0-.195-.068-.361-.205-.498-.137-.137-.303-.205-.498-.205h-9.844c-.195 0-.361.068-.498.205-.137.137-.205.303-.205.498v.469c0 .195.068.361.205.498.137.137.303.205.498.205h9.844zm0 3.75c.195 0 .361-.068.498-.205.137-.137.205-.303.205-.498v-.469c0-.195-.068-.361-.205-.498-.137-.137-.303-.205-.498-.205h-9.844c-.195 0-.361.068-.498.205-.137.137-.205.303-.205.498v.469c0 .195.068.361.205.498.137.137.303.205.498.205h9.844z" transform="translate(-106 -56)"/></g></g></svg>',
		'page'    => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M443.687 93c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993V70.734c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm0-1.875h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674V65.812c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm.938-20.625H439v-5.566c.156.039.293.117.41.234l4.922 4.922c.117.117.215.254.293.41zm-9.844-1.875c.117 0 .225-.049.322-.146.098-.098.147-.206.147-.323v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.323.098.097.206.146.323.146h6.562zm0 3.75c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h6.562zm6.914 11.25c.274 0 .518-.088.733-.264.215-.176.322-.4.322-.674v-5.625c0-.273-.107-.498-.322-.673-.215-.176-.46-.264-.733-.264h-12.89c-.274 0-.518.088-.733.264-.215.175-.322.4-.322.673v5.625c0 .274.107.498.322.674.215.176.46.264.733.264h12.89zm-.82-1.875h-11.25V78h11.25v3.75zm1.406 7.5c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-4.687c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h4.687z" transform="translate(-424 -63)"/></g></g></svg>',
		'post'    => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M568.687 93c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993V70.734c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm0-1.875h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674V65.812c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm.938-20.625H564v-5.566c.156.039.293.117.41.234l4.922 4.922c.117.117.215.254.293.41zm-9.844-1.875c.117 0 .225-.049.322-.146.098-.098.147-.206.147-.323v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.323.098.097.206.146.323.146h6.562zm0 3.75c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h6.562zm0 7c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h6.562zm0 4c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h6.562zm0 4c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-6.562c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h6.562zm7.5-8.125c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-4.687c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h4.687zm0 4c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-4.687c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h4.687zm0 4c.117 0 .225-.049.322-.147.098-.097.147-.205.147-.322v-.937c0-.117-.049-.225-.147-.323-.097-.097-.205-.146-.322-.146h-4.687c-.117 0-.225.049-.323.146-.097.098-.146.206-.146.323v.937c0 .117.049.225.146.322.098.098.206.147.323.147h4.687z" transform="translate(-549 -63)"/></g></g></svg>',
		'pdf'     => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M125.687 148c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H121v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-14.585-3.743l.171-.007c.547-.078 1.084-.41 1.612-.996.527-.586 1.142-1.485 1.845-2.695l.88-.293c1.796-.586 3.105-.957 3.925-1.114.625.352 1.29.635 1.992.85.703.215 1.3.322 1.787.322.489 0 .86-.146 1.114-.44.254-.292.361-.634.322-1.025-.04-.39-.156-.683-.352-.879-.586-.586-2.03-.722-4.336-.41-1.171-.703-2.05-1.816-2.636-3.34l.058-.175c.196-.782.313-1.387.352-1.817.117-.742.117-1.328 0-1.758-.078-.546-.313-.908-.703-1.084-.39-.175-.791-.195-1.201-.058-.41.137-.655.38-.733.732-.156.508-.176 1.153-.058 1.934.078.664.254 1.543.527 2.637-.898 2.187-1.719 3.906-2.461 5.156-2.07 1.094-3.223 2.129-3.457 3.105-.04.352.088.674.38.967.294.293.675.42 1.143.38l-.171.008zm5.562-11.316c-.156-.43-.234-.996-.234-1.699 0-.703.039-1.055.117-1.055v-.058c.234 0 .361.39.38 1.172.02.781-.068 1.328-.263 1.64zm-1.758 6.563c.508-.938 1.055-2.188 1.64-3.75.548 1.015 1.231 1.836 2.052 2.46-.703.118-1.68.43-2.93.938l-.762.352zm8.79-.293c-.157.039-.391.02-.704-.059-.469-.078-1.015-.254-1.64-.527.703-.078 1.289-.078 1.757 0 .352.078.596.166.733.264.136.097.146.185.03.263-.04.04-.099.059-.177.059zm-12.71 3.995l-.006-.01c.196-.548.82-1.29 1.875-2.227l.176-.176c-.39.625-.761 1.133-1.113 1.523-.234.313-.45.547-.645.703-.195.157-.293.215-.293.176l.006.011z" transform="translate(-106 -118)"/></g></g></svg>',
		'doc'     => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M125.687 210c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H121v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-11.25-3.75c.157 0 .303-.049.44-.147.137-.097.225-.224.264-.38l1.875-7.032c.078-.43.156-.879.234-1.347l.117.586c.156.625.684 2.597 1.582 5.918l.469 1.875c.039.156.127.283.264.38.136.098.283.147.44.147h.82c.156 0 .331-.049.527-.147.195-.097.312-.224.351-.38l2.52-9.844c.039-.235-.01-.44-.147-.615-.136-.176-.322-.264-.556-.264h-.41c-.157 0-.303.049-.44.146-.137.098-.225.225-.264.381-.024.173-.07.422-.138.746l-.075.35c-.15.688-.372 1.631-.666 2.83-.664 3.047-1.015 4.688-1.054 4.922-.078-.469-.196-.918-.352-1.348-.469-1.562-1.172-4.062-2.11-7.5-.038-.156-.116-.283-.233-.38-.118-.098-.254-.147-.41-.147h-.41c-.157 0-.294.049-.411.146-.117.098-.195.225-.234.381-1.055 3.79-1.7 6.192-1.934 7.207-.195.782-.332 1.368-.41 1.758v.117l-.059-.586c-.117-.78-.761-3.613-1.933-8.496-.04-.156-.127-.283-.264-.38-.137-.098-.283-.147-.44-.147h-.41c-.234 0-.42.088-.556.264-.137.175-.186.38-.147.615l2.52 9.844c0 .156.068.283.205.38.137.098.283.147.44.147h.995z" transform="translate(-106 -180)"/></g></g></svg>',
		'xls'     => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M280.687 210c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H276v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-12.187-3.75c.234 0 .43-.098.586-.293.664-1.094 1.367-2.168 2.11-3.223.507-.703.859-1.25 1.054-1.64.898 1.601 1.777 2.968 2.637 4.101l.527.762c.156.195.352.293.586.293h.234c.274 0 .479-.117.616-.352.136-.234.127-.468-.03-.703l-3.398-5.273 2.93-4.805c.156-.234.166-.469.029-.703-.137-.234-.342-.352-.615-.352h-.235c-.273 0-.469.118-.586.352l-1.347 1.992c-.664 1.016-1.114 1.797-1.348 2.344l-.059-.117c-.43-.781-.84-1.465-1.23-2.051l-1.406-2.168c-.118-.234-.313-.352-.586-.352h-.235c-.273 0-.478.118-.615.352-.137.234-.127.469.03.703l2.988 4.805-3.457 5.215c-.157.273-.166.527-.03.761.137.235.342.352.616.352h.234z" transform="translate(-261 -180)"/></g></g></svg>',
		'zip'     => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M443.687 209c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H439v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h4.688v1.875h1.875v-1.875h3.75v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-8.437-22.5v-1.875h-1.875v1.875h1.875zm-1.875 1.875v-1.875H431.5v1.875h1.875zm1.875 1.875V186.5h-1.875v1.875h1.875zm-1.875 1.875v-1.875H431.5v1.875h1.875zm1.875 1.875v-1.875h-1.875v1.875h1.875zm-1.875 11.25c.977 0 1.768-.371 2.373-1.113.605-.743.81-1.602.615-2.578l-.996-5.098c-.039-.195-.127-.342-.264-.44-.136-.097-.283-.146-.439-.146h-1.289v-1.875H431.5V194l-1.113 5.684c-.196.976.01 1.835.615 2.578.605.742 1.396 1.113 2.373 1.113zm0-1.523c-.508 0-.947-.157-1.318-.47-.371-.312-.557-.683-.557-1.113 0-.43.186-.8.557-1.113.37-.312.82-.469 1.347-.469.528 0 .977.157 1.348.47.371.312.557.683.557 1.112 0 .43-.186.801-.557 1.114-.371.312-.83.469-1.377.469z" transform="translate(-424 -179)"/></g></g></svg>',
		'ppt'     => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M125.687 272c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H121v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-11.015-3.75c.195 0 .361-.068.498-.205.137-.137.205-.303.205-.498v-3.516h2.578c1.172 0 2.129-.38 2.871-1.142.742-.762 1.113-1.729 1.113-2.9 0-1.173-.351-2.12-1.054-2.843-.703-.722-1.68-1.084-2.93-1.084h-3.75c-.195 0-.361.069-.498.206-.137.136-.205.302-.205.498v10.78c0 .196.068.362.205.499.137.137.303.205.498.205h.469zm2.988-5.8h-2.285v-4.923h2.344c.742 0 1.338.215 1.787.645.449.43.674 1.025.674 1.787s-.235 1.377-.703 1.846c-.43.43-1.036.644-1.817.644z" transform="translate(-106 -242)"/></g></g></svg>',
		'video'   => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M125.687 346c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H121v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-8.027-3.75c.43 0 .781-.137 1.055-.41.273-.274.41-.625.41-1.055v-.586l1.64 1.64c.274.274.606.41.997.41h1.582c.39 0 .722-.136.996-.41.273-.273.41-.605.41-.995v-6.563c0-.39-.137-.722-.41-.996-.274-.273-.606-.41-.996-.41h-1.582c-.39 0-.723.137-.996.41l-1.641 1.64v-.585c0-.43-.137-.781-.41-1.055-.274-.273-.625-.41-1.055-.41h-6.445c-.43 0-.781.137-1.055.41-.273.274-.41.625-.41 1.055v6.445c0 .43.137.781.41 1.055.274.273.625.41 1.055.41h6.445zm-.41-1.875h-5.625v-5.625h5.625v5.625zm5.625 0h-.938l-2.812-2.402v-.82l2.812-2.403h.938v5.625z" transform="translate(-106 -316)"/></g></g></svg>',
		'image'   => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M280.687 344c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H276v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zM268.441 329c1.016 0 1.895-.371 2.637-1.113.742-.743 1.113-1.621 1.113-2.637s-.37-1.895-1.113-2.637c-.742-.742-1.621-1.113-2.637-1.113-1.015 0-1.894.371-2.636 1.113-.743.742-1.114 1.621-1.114 2.637s.371 1.894 1.114 2.637c.742.742 1.62 1.113 2.636 1.113zm0-1.875c-.507 0-.947-.186-1.318-.557-.371-.37-.557-.81-.557-1.318s.186-.947.557-1.318c.371-.371.81-.557 1.318-.557s.948.186 1.319.557c.37.37.556.81.556 1.318s-.185.947-.556 1.318c-.371.371-.81.557-1.319.557zm11.25 13.125v-8.438l-3.222-3.28c-.157-.118-.332-.177-.528-.177-.195 0-.351.059-.468.176l-5.157 5.156-2.285-2.343c-.156-.117-.332-.176-.527-.176s-.371.078-.527.234l-2.227 2.227-.059 6.62h15zm-1.875-1.875h-11.25v-3.926l.938-.937 2.812 2.812 5.625-5.625 1.875 1.875v5.8z" transform="translate(-261 -314)"/></g></g></svg>',
		'audio'   => '<svg class="tva-icon" viewBox="0 0 23 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M443.687 344c.782 0 1.446-.274 1.993-.82.547-.547.82-1.211.82-1.993v-19.453c0-.78-.273-1.445-.82-1.992l-4.922-4.922c-.547-.547-1.211-.82-1.992-.82h-11.954c-.78 0-1.445.273-1.992.82-.547.547-.82 1.211-.82 1.992v24.375c0 .782.273 1.446.82 1.993.547.546 1.211.82 1.992.82h16.875zm.938-22.5H439v-5.625c.156.078.293.176.41.293l4.922 4.922c.117.117.215.254.293.41zm-.938 20.625h-16.875c-.273 0-.498-.088-.673-.264-.176-.176-.264-.4-.264-.674v-24.375c0-.273.088-.498.264-.673.175-.176.4-.264.673-.264h10.313v6.094c0 .39.137.722.41.996.274.273.606.41.996.41h6.094v17.812c0 .274-.088.498-.264.674-.176.176-.4.264-.674.264zm-5.039-3.75c.196 0 .352-.059.47-.176 1.132-.742 2.02-1.719 2.665-2.93.645-1.21.967-2.48.967-3.808 0-1.484-.361-2.852-1.084-4.102-.723-1.25-1.69-2.226-2.9-2.93-.235-.156-.47-.204-.704-.146-.234.059-.41.195-.527.41-.117.215-.146.44-.088.674.059.235.186.41.381.528.977.585 1.748 1.376 2.315 2.373.566.996.85 2.04.85 3.134 0 1.094-.255 2.11-.763 3.047-.507.938-1.21 1.719-2.109 2.344-.195.117-.312.293-.352.527-.039.235.02.469.176.703.157.235.39.352.703.352zm-1.582-2.285c.196.039.371 0 .528-.117.742-.508 1.328-1.163 1.758-1.963.43-.801.644-1.67.644-2.608 0-.937-.244-1.826-.732-2.666-.489-.84-1.143-1.513-1.963-2.021-.196-.117-.41-.147-.645-.088-.234.059-.42.195-.556.41-.137.215-.166.44-.088.674.078.234.215.41.41.527.547.352.986.81 1.318 1.377.332.567.498 1.162.498 1.787s-.146 1.211-.44 1.758c-.292.547-.693.996-1.2 1.348-.196.117-.313.302-.352.556-.039.254.01.489.147.704.136.214.36.322.673.322zm-3.925.879c.195 0 .36-.069.498-.205.136-.137.205-.303.205-.498v-8.907c0-.195-.069-.361-.205-.498-.137-.136-.303-.205-.498-.205-.196 0-.352.078-.47.235L430.095 329h-1.64c-.196 0-.362.068-.499.205-.137.137-.205.303-.205.498v4.219c0 .195.068.361.205.498.137.137.303.205.498.205h1.64l2.579 2.11c.117.156.273.234.469.234zm2.402-3.106c.156 0 .312-.039.469-.117.39-.234.703-.557.937-.967.235-.41.352-.86.352-1.347 0-.489-.127-.948-.381-1.377-.254-.43-.615-.762-1.084-.996-.195-.118-.41-.137-.645-.059-.234.078-.41.225-.527.44-.117.214-.137.439-.059.673.079.235.215.39.41.469.352.195.528.479.528.85 0 .37-.156.634-.469.79-.195.157-.322.352-.38.587-.06.234-.01.468.146.703.156.234.39.351.703.351zm-3.457.117l-1.524-1.113h-1.054v-2.11h1.054l1.524-1.113v4.336z" transform="translate(-424 -314)"/></g></g></svg>',
		'url'     => '<svg class="tva-icon" viewBox="0 0 25 30"><g fill="none" fill-rule="evenodd"><g fill="currentColor" fill-rule="nonzero"><path d="M590.177 179v2H581l-.117.007c-.497.057-.883.48-.883.993v22l.007.117c.057.497.48.883.993.883h15.5l.117-.007c.497-.057.883-.48.883-.993v-13.543h2V204l-.005.176c-.091 1.575-1.397 2.824-2.995 2.824H581l-.176-.005c-1.575-.091-2.824-1.397-2.824-2.995v-22l.005-.176c.091-1.575 1.397-2.824 2.995-2.824h9.177zm12.323-2v9c0 .552-.448 1-1 1-.513 0-.936-.386-.993-.883L600.5 186v-5.918l-11.24 11.241c-.391.39-1.024.39-1.415 0-.36-.36-.388-.927-.083-1.32l.083-.094L598.753 179h-5.227c-.513 0-.935-.386-.993-.883l-.007-.117c0-.513.386-.936.884-.993l.116-.007h8.974z" transform="translate(-578 -177)"/></g></g></svg>',
	);

	/**
	 * Stores relevant data
	 *
	 * @var array
	 */
	protected $data = array();

	public static $meta_fields = array(
		'order'  => 'int',
		'type'   => 'string',
		'icon'   => 'string',
		'config' => 'array',
	);

	/**
	 * TVA_Resource constructor.
	 *
	 * @param WP_Post|string|int|array $post can be a WP_Post object, or a data array
	 */
	public function __construct( $post ) {
		if ( is_array( $post ) ) {
			$this->data = $post;
		} else {
			$post       = get_post( $post );
			$this->data = array(
				'id'        => $post->ID,
				'lesson_id' => $post->post_parent,
				'title'     => $post->post_title,
				'content'   => $post->post_content,
			);
			/* handle meta */
			foreach ( static::$meta_fields as $field => $cast ) {
				$meta_field           = "tva_res_{$field}";
				$this->data[ $field ] = $this->cast( $post->$meta_field, $cast );
			}
		}
	}

	/**
	 * Casts a value to a specific type
	 *
	 * @param mixed  $value
	 * @param string $type
	 *
	 * @return mixed
	 */
	protected function cast( $value, $type ) {
		switch ( $type ) {
			case 'int':
			case 'integer':
				$value = (int) $value;
				break;
			case 'str':
			case 'string':
				$value = (string) $value;
				break;
			case 'array':
				$value = empty( $value ) ? array() : (array) $value;
				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * Return this object's data as an array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Magic getter. Get any field from this->data array
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : null;
	}

	/**
	 * Magic setter. Sets a field on data
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Saves the resources. Creates or updates a WP_Post entry
	 *
	 * @return self
	 */
	public function save() {
		$post_data = array(
			'post_type'    => static::POST_TYPE,
			'post_parent'  => $this->lesson_id,
			'post_title'   => $this->title,
			'post_content' => $this->content,
		);
		if ( ! $this->id ) {
			$post_id = wp_insert_post( $post_data );
		} else {
			$post_id = $this->id;
			wp_update_post( $post_data + array( 'ID' => $this->id ) );
		}

		$this->id = $post_id;

		/* rest of the meta fields */
		foreach ( static::$meta_fields as $field => $cast ) {
			$meta_field   = "tva_res_{$field}";
			$this->$field = $this->cast( $this->$field, $cast ); // make sure correct data type are also stored in $this->data
			update_post_meta( $post_id, $meta_field, $this->$field );
		}

		return $this;
	}

	/**
	 * Instantiate a single resource object from an array of data or a WP post (or post id)
	 *
	 * @param WP_Post|int|string|array $post
	 *
	 * @return self
	 */
	public static function one( $post ) {
		return new static( $post );
	}

	/**
	 * Get an array of resource instances for a lesson
	 *
	 * @param int   $lesson_id
	 * @param array $args additional get_post() arguments
	 *
	 * @return self[]
	 */
	public static function all( $lesson_id, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'post_status' => TVA_Post::$accepted_statuses,
			'post_type'   => static::POST_TYPE,
			'post_parent' => (int) $lesson_id,
			'numberposts' => - 1,
			'meta_key'    => 'tva_res_order',
			'orderby'     => 'meta_value_num',
			'order'       => 'ASC',
		) );

		return array_map( function ( $post ) {
			return TVA_Resource::one( $post );
		}, get_posts( $args ) );
	}

	/**
	 * Duplicate a resource to a lesson
	 *
	 * @param int $lesson_id
	 *
	 * @return self
	 */
	public function duplicate( $lesson_id ) {
		return TVA_Resource::one( [
			'lesson_id' => $lesson_id,
			'title'     => $this->title,
			'content'   => $this->content,
			'order'     => $this->order,
			'type'      => $this->type,
			'icon'      => $this->icon,
			'config'    => $this->config,
		] )->save();
	}

	/**
	 * Serialize as this object's data
	 *
	 * @return array|mixed
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->get_data();
	}

	/**
	 * Get a URL to this resource
	 *
	 * @return string|null the url or null for failure
	 */
	public function get_url() {
		$config = $this->config;
		$url    = null;
		switch ( $this->type ) {
			case 'file':
				if ( isset( $config['attachment']['id'] ) ) {
					$url = wp_get_attachment_url( $this->config['attachment']['id'] );
				}
				break;
			case 'url':
				if ( isset( $config['url'] ) ) {
					$url = $config['url'];
				}
				break;
			case 'content':
				if ( isset( $config['post']['id'] ) ) {
					$url = get_permalink( (int) $config['post']['id'] );
				}
				break;
			default:
				break;
		}

		return $url;
	}

	/**
	 * Generate a download key based on resource id, lesson_id, attachment id and user id
	 *
	 * @return false|string
	 */
	protected function get_download_key() {
		$lesson_id     = $this->lesson_id;
		$resource_id   = $this->id;
		$attachment_id = $this->config['attachment']['id'];
		$user_id       = get_current_user_id();

		return wp_hash( $resource_id . '--' . $lesson_id . '--' . $attachment_id . '--' . $user_id, 'nonce' );
	}

	/**
	 * Checks whether or not this resource can be downloaded by the user
	 *
	 * @return bool
	 */
	public function is_downloadable() {
		$config = $this->config;

		return $this->type === 'file' && ! empty( $config['attachment']['id'] );
	}

	/**
	 * Build a URL that will download the resource file when accessed
	 *
	 * @return string|null download URL or null if the resource is not downloadable
	 */
	public function get_download_url() {
		if ( ! $this->is_downloadable() ) {
			return null;
		}

		return add_query_arg( array(
			'tva_res_download' => $this->id,
			'r'                => $this->get_download_key(),
		), get_permalink( $this->lesson_id ) );
	}

	/**
	 * Send the actual download the the client
	 *
	 * @param string $key key from request
	 */
	public function send_download( $key ) {
		if ( $this->is_downloadable() && $key === $this->get_download_key() ) {
			$file = get_attached_file( $this->config['attachment']['id'], true );
			if ( $file && is_readable( $file ) ) {
				$filename = sprintf( '"%s"', addcslashes( basename( $file ), '"\\' ) );
				$size     = filesize( $file );

				header( 'Content-Description: File Transfer' );
				header( 'Content-Type: application/octet-stream' );
				header( 'Content-Disposition: attachment; filename=' . $filename );
				header( 'Content-Transfer-Encoding: binary' );
				header( 'Connection: Keep-Alive' );
				header( 'Expires: 0' );
				header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
				header( 'Pragma: public' );
				header( 'Content-Length: ' . $size );

				readfile( $file );
				exit();
			}
		}

		// else just redirect to the original lesson
		wp_redirect( get_permalink( $this->lesson_id ) );
		exit();
	}

	/**
	 * Try to download a resource. Called during the WP `init` hook
	 */
	public static function on_init_try_download() {
		if ( isset( $_GET['tva_res_download'], $_GET['r'] ) && ! is_admin() ) {
			$resource_id = (int) $_GET['tva_res_download'];
			$key         = sanitize_text_field( $_GET['r'] );

			$post = get_post( $resource_id );
			if ( $post && $post->post_type === static::POST_TYPE ) {
				static::one( $resource_id )->send_download( $key );
			}
		}
	}

	/**
	 * Get the html for the icon
	 *
	 * @return string the html
	 */
	public function icon_html() {
		$icon = (string) $this->icon;
		/* this is just to support RC backwards-compat. Icons are not saved as HTML anymore */
		if ( strpos( $icon, '<' ) === 0 ) {
			return $icon;
		}

		if ( ! isset( static::$icons[ $icon ] ) ) {
			$icon = 'default';
		}

		return static::$icons[ $icon ];
	}

	/**
	 * Deletes a resource
	 *
	 * @param bool $force whether or not to delete it permanently
	 *
	 * @return bool deletion result
	 */
	public function delete( $force = false ) {
		if ( ! $this->id ) {
			return true;
		}

		return (bool) wp_delete_post( $this->id, $force );
	}
}
