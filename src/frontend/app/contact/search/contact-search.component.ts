import { Component, OnInit, ViewChild, EventEmitter, ViewContainerRef, TemplateRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { MatDialogRef } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { ActivatedRoute, Router } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { ContactsCriteriaToolComponent } from './criteria-tool/contacts-criteria-tool.component';
import { SearchResultListComponent } from './../../search/result-list/search-result-list.component';
import { NotificationService } from '@service/notification/notification.service';
import { PrivilegeService } from '@service/privileges.service';
import {
    ContactSearchResultListComponent
} from "@appRoot/contact/search/result-list/contact-search-result-list.component";

@Component({
    selector: 'app-contact-search',
    templateUrl: 'contact-search.component.html',
    styleUrls: ['contact-search.component.scss']
})

export class ContactSearchComponent implements OnInit {

    searchTerm: string = '';
    searchTemplateId: string = null;

    filtersChange = new EventEmitter();

    dialogRef: MatDialogRef<any>;
    loadingResult: boolean = false;

    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;

    @ViewChild('adminMenuTemplateContacts', { static: true }) adminMenuTemplateContacts: TemplateRef<any>;
    @ViewChild('contactSearchResultList', { static: false }) contactSearchResultList: ContactSearchResultListComponent;
    @ViewChild('appContactCriteriaTool', { static: true }) appContactCriteriaTool: ContactsCriteriaToolComponent;

    constructor(
        _activatedRoute: ActivatedRoute,
        public translate: TranslateService,
        private headerService: HeaderService,
        public viewContainerRef: ViewContainerRef,
        public appService: AppService,
        public functions: FunctionsService,
        private notify: NotificationService,
        private privilegeService: PrivilegeService,
        private router: Router
    ) {
        _activatedRoute.queryParams.subscribe(params => {
            if (!this.functions.empty(params.searchTemplateId)) {
                this.searchTemplateId = params.searchTemplateId;
                window.history.replaceState({}, document.title, window.location.pathname + window.location.hash.split('?')[0]);
            } else if (!this.functions.empty(params.value)) {
                this.searchTerm = params.value;
            }
        });
    }

    ngOnInit(): void {
        this.headerService.sideBarAdmin = true;
        this.headerService.injectInSideBarLeft(this.adminMenuTemplateContacts, this.viewContainerRef, 'adminMenuContacts');
        this.headerService.setHeader(this.translate.instant('lang.searchContactAdvanced'), '', '');
        if (this.privilegeService.getCurrentUserMenus().find((privilege: any) => privilege.id === 'admin_search_contacts') === undefined) {
            this.notify.handleSoftErrors(this.translate.instant('lang.cannotAccessPage'));
            this.router.navigate(['/home']);
        }
    }

    setLaunchWithSearchTemplate(templates: any) {
        if (this.searchTemplateId !== null) {
            const template = templates.find((itemTemplate: any) => itemTemplate.id === this.searchTemplateId);
            if (template !== undefined) {
                this.appContactCriteriaTool.selectSearchTemplate(template);
                this.appContactCriteriaTool.getCurrentCriteriaValues();
            } else {
                this.notify.error(this.translate.instant('lang.noTemplateFound'));
            }
        }
    }

    initSearch() {
        if (this.searchTemplateId === null) {
            this.contactSearchResultList.initSavedCriteria();
        }
    }
}
