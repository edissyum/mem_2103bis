import { Component, OnInit, AfterViewInit, Input, EventEmitter, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { DashboardService } from '@appRoot/home/dashboard/dashboard.service';
import { catchError, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-tile',
    templateUrl: 'tile.component.html',
    styleUrls: ['tile.component.scss']
})
export class TileDashboardComponent implements OnInit, AfterViewInit {

    @Input() view: string = 'list';

    @Input() tile: any = null;

    @Output() hasError = new EventEmitter<any>();

    loading: boolean = true;
    onError: boolean = false;
    errorMessage: string = '';

    resources: any[] = [];
    countResources: number = 0;
    route: string = null;
    viewDocRoute: string = null;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public appService: AppService,
        public dashboardService: DashboardService,
        private functionsService: FunctionsService
    ) { }

    ngOnInit(): void { }

    async ngAfterViewInit(): Promise<void> {
        await this['get_' + this.view]();
        this.route = this.tile.views.find((viewItem: any) => viewItem.id === this.view).route;
        this.viewDocRoute = this.tile.views.find((viewItem: any) => viewItem.id === this.view).viewDocRoute;
        this.loading = false;
    }

    async changeView(view: string, extraParams: any) {
        this.view = null;
        this.loading = true;
        this.view = view;
        this.route = this.tile.views.find((viewItem: any) => viewItem.id === this.view).route;
        this.loading = false;
    }

    async get_list(extraParams: any) {
        return new Promise((resolve) => {
            this.http.get(`../rest/tiles/${this.tile.id}`).pipe(
                tap((data: any) => {
                    const resources = data.tile.resources.map((resource: any) => {
                        let contactLabel = '';
                        let contactTitle = '';
                        if (resource.correspondents.length === 1) {
                            contactLabel = resource.correspondents[0];
                            contactTitle = this.translate.instant('lang.contact') + ': ' + resource.correspondents[0];
                        } else if (resource.correspondents.length > 1) {
                            contactLabel = resource.correspondents.length + ' ' + this.translate.instant('lang.contacts');
                            contactTitle = resource.correspondents;
                        }
                        return {
                            ...resource,
                            contactLabel: contactLabel,
                            contactTitle: contactTitle
                        };
                    });
                    this.resources = resources;
                    resolve(true);
                }),
                catchError((err: any) => {
                    console.log(err);
                    this.notify.error(this.translate.instant('lang.tileLoadError', { 0: (this.tile.position + 1) }));
                    this.onError = true;
                    this.hasError.emit({id: this.tile.id, error: this.onError});
                    this.errorMessage = err.error.errors;
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async get_summary(extraParams: any) {
        return new Promise((resolve) => {
            this.http.get(`../rest/tiles/${this.tile.id}`).pipe(
                tap((data: any) => {
                    this.countResources = data.tile.resourcesNumber;
                    resolve(true);
                }),
                catchError((err: any) => {
                    console.log(err);
                    this.notify.error(this.translate.instant('lang.tileLoadError', { 0: (this.tile.position + 1) }));
                    this.onError = true;
                    this.hasError.emit({id: this.tile.id, error: this.onError});
                    this.errorMessage = err.error.errors;
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async get_chart(extraParams: any) {
        return new Promise((resolve) => {
            this.http.get(`../rest/tiles/${this.tile.id}`).pipe(
                tap((data: any) => {
                    this.resources = data.tile.resources.map((item: any) => ({
                        ...item,
                        name: !this.functionsService.empty(item.name) ? item.name : this.translate.instant('lang.undefined')
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    console.log(err);
                    this.notify.error(this.translate.instant('lang.tileLoadError', { 0: (this.tile.position + 1) }));
                    this.onError = true;
                    this.hasError.emit({id: this.tile.id, error: this.onError});
                    this.errorMessage = err.error.errors;
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }
}
