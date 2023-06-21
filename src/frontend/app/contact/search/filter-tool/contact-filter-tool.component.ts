import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { tap } from "rxjs/operators";
import { isModuleWithProvidersNotGeneric } from "@angular/core/schematics/migrations/module-with-providers/util";

@Component({
    selector: 'app-contact-filter-tool-search',
    templateUrl: 'contact-filter-tool.component.html',
    styleUrls: ['contact-filter-tool.component.scss']
})
export class ContactFilterToolComponent implements OnInit {

    @Input() filters: any = {};
    @Input() isLoadingResults: boolean = false;

    @Output() filterChanged = new EventEmitter<any>();
    customFields = [];
    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public appService: AppService,
        public functions: FunctionsService) { }

    ngOnInit(): void {
        this.http.get('../rest/contactsCustomFields').pipe(
            tap((data: any) => {
                this.customFields = data.customFields;
            })
        ).subscribe();
    }

    setfilters(filters: any) {
        this.filters = filters;
    }

    getCustomLabel(custom: string) {
        const customId = custom.replace('contactCustomField_', '');
        let label = '';
        this.customFields.forEach((element: any) => {
            if (element.id.toString() === customId.toString()) {
                label = element.label;
            }
        });
        return label;
    }

    toggleFilter(key: string, index: number) {
        this.getFiltersNotEmpty(key)[index].selected = !this.getFiltersNotEmpty(key)[index].selected;
        this.filterChanged.emit();
    }

    toggleDisplay(key: string) {
        this.filters[key]['expand'] = !this.filters[key]['expand'];
    }

    getRealCount(key: string) {
        return this.filters[key]['values'].filter((item: any) => item.count > 0 || (item.count === 0 && item.selected)).length;
    }

    getFiltersNotEmpty(key: string) {
        return this.filters[key]['values'].filter((item: any) => item.count > 0 || (item.count === 0 && item.selected));
    }
}
